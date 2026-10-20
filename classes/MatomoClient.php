<?php namespace Pensoft\Matomo\Classes;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Pensoft\Matomo\Exceptions\MatomoException;

/**
 * Minimal Matomo Reporting API client. Every call is a POST so the token
 * never appears in a URL (and so in no server log).
 */
class MatomoClient
{
    /** @var string */
    protected $url;

    /** @var string */
    protected $token;

    /** @var Client */
    protected $http;

    public function __construct($url, $token, $timeout = 20, Client $http = null)
    {
        $this->url = rtrim((string) $url, '/') . '/index.php';
        $this->token = (string) $token;
        $this->http = $http ?: new Client(['timeout' => $timeout]);
    }

    /**
     * Calls an API method and returns its decoded JSON.
     *
     * @throws MatomoException on a network error or an API error response
     */
    public function call($method, array $params = [])
    {
        $form = array_merge($params, [
            'module'     => 'API',
            'method'     => $method,
            'format'     => 'json',
            'token_auth' => $this->token,
        ]);

        try {
            $response = $this->http->request('POST', $this->url, ['form_params' => $form]);
        } catch (GuzzleException $e) {
            throw new MatomoException(sprintf('Matomo request %s failed: %s', $method, $this->hideToken($e->getMessage())));
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new MatomoException(sprintf('Matomo %s returned no JSON: %s', $method, mb_substr(strip_tags($body), 0, 200)));
        }
        if (is_array($data) && isset($data['result']) && $data['result'] === 'error') {
            throw new MatomoException(sprintf('Matomo %s: %s', $method, isset($data['message']) ? $data['message'] : 'unknown error'));
        }
        return $data;
    }

    /**
     * IDs of the sites registered under this URL (empty when none).
     */
    public function siteIdsForUrl($url)
    {
        $rows = $this->call('SitesManager.getSitesIdFromSiteUrl', ['url' => $url]);
        $ids = [];
        foreach ((array) $rows as $row) {
            if (isset($row['idsite'])) {
                $ids[] = (int) $row['idsite'];
            }
        }
        return $ids;
    }

    /**
     * Registers a site and returns its new ID.
     */
    public function addSite($name, array $urls, array $settings)
    {
        $params = array_merge($settings, ['siteName' => $name]);
        foreach (array_values($urls) as $i => $u) {
            $params['urls[' . $i . ']'] = $u;
        }
        $data = $this->call('SitesManager.addSite', $params);
        $id = is_array($data) && isset($data['value']) ? (int) $data['value'] : (int) $data;
        if ($id < 1) {
            throw new MatomoException('Matomo SitesManager.addSite returned no site ID.');
        }
        return $id;
    }

    /**
     * Gives a Matomo user access ("view", "write" or "admin") to a site.
     * $passwordConfirmation: the token owner's password, for Matomo
     * versions that ask for it on access changes.
     */
    public function setUserAccess($login, $access, $siteId, $passwordConfirmation = null)
    {
        $params = ['userLogin' => $login, 'access' => $access, 'idSites' => $siteId];
        if ($passwordConfirmation !== null && $passwordConfirmation !== '') {
            $params['passwordConfirmation'] = $passwordConfirmation;
        }
        $this->call('UsersManager.setUserAccess', $params);
    }

    protected function hideToken($text)
    {
        return $this->token !== '' ? str_replace($this->token, '***', $text) : $text;
    }
}
