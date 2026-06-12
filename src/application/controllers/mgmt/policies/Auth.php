<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_MgmtController.php';

class Auth extends MY_MgmtController
{
    public function get($apiId)
    {
        $this->requireApiAccess($apiId);
        $auth = $this->store->loadPhp("{$this->store->getApiDir($apiId)}/{$this->configFiles['auth']}");
        if (isset($auth['jwt_key'])) {
            $auth['jwt_key'] = '***********';
        }
        if (empty($auth['mode'])) {
            $auth['mode'] = (!empty($auth['loginQuery']) || !empty($auth['loginMethods'])) ? 'dbAuth' : 'none';
        }
        HttpResp::json_out(200, $auth);
    }

    public function update($apiId)
    {
        $this->requireApiAccess($apiId);
        $payload = $this->validatePayload();
        $policy = json_decode(json_encode($payload), true);
        $disk = $this->authPolicyToDisk($policy, $apiId);
        $this->store->savePhp("{$this->store->getApiDir($apiId)}/{$this->configFiles['auth']}", $disk);
        $this->store->touchUpdated($apiId);
        HttpResp::json_out(200, $policy);
    }

    private function authPolicyToDisk(array $policy, string $apiId): array
    {
        $mode = $policy['mode'] ?? 'none';
        if ($mode === 'none') {
            return ['mode' => 'none', 'allowGuest' => true];
        }
        if ($mode === 'dbAuth') {
            $dbAuth = $policy['dbAuth'] ?? $policy;
            $disk = [
                'mode' => 'dbAuth',
                'validity' => $dbAuth['validity'] ?? 3600,
            ];

            if (!empty($dbAuth['loginMethods']) && is_array($dbAuth['loginMethods'])) {
                $disk['loginMethods'] = $this->loginMethodsToDisk($dbAuth['loginMethods']);
            } else {
                $disk['loginQuery'] = $dbAuth['login']['sql'] ?? $dbAuth['loginQuery'] ?? null;
            }

            $existing = $this->store->loadPhp("{$this->store->getApiDir($apiId)}/{$this->configFiles['auth']}");
            $hasLogin = !empty($disk['loginQuery']) || !empty($disk['loginMethods']);
            if ($hasLogin && empty($existing['jwt_key'])) {
                $disk['jwt_key'] = bin2hex(random_bytes(32));
            } elseif (!empty($existing['jwt_key'])) {
                $disk['jwt_key'] = $existing['jwt_key'];
            }
            return $disk;
        }
        return $policy;
    }

    /**
     * @param array<string,array<string,mixed>> $loginMethods
     * @return array<string,array<string,mixed>>
     */
    private function loginMethodsToDisk(array $loginMethods): array
    {
        $disk = [];
        foreach ($loginMethods as $name => $method) {
            if (!is_array($method)) {
                continue;
            }
            $entry = [
                'loginQuery' => $method['sql'] ?? $method['loginQuery'] ?? null,
            ];
            if (isset($method['validity'])) {
                $entry['validity'] = (int) $method['validity'];
            }
            if (!empty($method['fields']) && is_array($method['fields'])) {
                $entry['fields'] = array_values($method['fields']);
            }
            $disk[$name] = $entry;
        }
        return $disk;
    }
}
