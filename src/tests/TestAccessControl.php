<?php

require_once __DIR__ . '/../application/third_party/dbAPI/Autoloader.php';
dbAPI\Autoloader::register();

if (!defined('APPPATH')) {
    define('APPPATH', __DIR__ . '/../application/');
}

use dbAPI\API\AccessControl;
use dbAPI\API\DBAPIRequest;
use dbAPI\API\Datamodel;
use dbAPI\API\FilterParser;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

class TestAccessControl extends TestCase
{
    public function testWhenSkipsRuleWhenClaimMismatch(): void
    {
        $payload = (object) ['role' => 'user', 'userId' => 5];
        $rules = [
            ['pattern' => '/*', 'methods' => '*', 'allow' => true, 'when' => ['role' => 'admin']],
            ['pattern' => '/users/{{userId}}/*', 'methods' => '*', 'allow' => true],
        ];
        $userData = AccessControl::claimSubstitutions($payload);

        $this->assertNull(AccessControl::evaluatePathRules($rules, '/orders', 'GET', $userData, $payload));
        $this->assertTrue(AccessControl::evaluatePathRules($rules, '/users/5/orders', 'GET', $userData, $payload));
        $this->assertTrue(AccessControl::evaluatePathRules($rules, '/users/5/orders', 'GET', $userData, (object) ['role' => 'admin', 'userId' => 1]));
    }

    public function testDecodeJwtAcceptsXAuthorizationHeader(): void
    {
        $key = bin2hex(random_bytes(16));
        $token = JWT::encode(['userId' => 7, 'role' => 'user'], $key, 'HS256');
        $auth = ['mode' => 'dbAuth', 'jwt_key' => $key];
        $headers = ['X-Authorization' => 'Bearer ' . $token];

        $jwt = AccessControl::decodeJwt($auth, $headers);

        $this->assertTrue($jwt['valid']);
        $this->assertFalse($jwt['anonymous']);
        $this->assertSame(7, (int) $jwt['payload']->userId);
    }

    public function testResolveDefaultAccessRuleMigratesAllowGuest(): void
    {
        $this->assertSame('public', AccessControl::resolveDefaultAccessRule(['allowGuest' => true]));
        $this->assertSame('private', AccessControl::resolveDefaultAccessRule(['allowGuest' => false]));
        $this->assertSame('private', AccessControl::resolveDefaultAccessRule(['default_access_rule' => 'private']));
    }

    public function testTableAccessPublicAllowsAnonymousGet(): void
    {
        $auth = ['default_access_rule' => 'private'];
        $structure = [
            'products' => ['access' => 'public'],
        ];
        $payload = new stdClass();
        $this->assertTrue(AccessControl::evaluateTableAccess(
            $auth,
            $structure,
            '/products',
            'GET',
            $payload,
            false,
            true
        ));
        $this->assertFalse(AccessControl::evaluateTableAccess(
            $auth,
            $structure,
            '/products',
            'POST',
            $payload,
            false,
            true
        ));
    }

    public function testMandatoryFilterOverridesClientField(): void
    {
        $dm = Datamodel::init([
            'orders' => [
                'type' => 'table',
                'keyFld' => 'id',
                'fields' => ['id' => [], 'user_id' => []],
                'mandatoryFilter' => 'user_id={{userId}}',
            ],
        ]);
        $request = new DBAPIRequest('orders', 20);
        $request->set_filter_from_string('user_id=99');
        $payload = (object) ['userId' => 5];

        AccessControl::applyMandatoryFilter($request, $dm, [], $payload);

        $where = FilterParser::compile(FilterParser::normalize($request->filter), 'orders');
        $this->assertStringContainsString('user_id', $where);
        $this->assertStringNotContainsString('99', $where);
        $this->assertStringContainsString('5', $where);
    }

    public function testMandatoryAssignOverridesBody(): void
    {
        $dm = Datamodel::init([
            'orders' => [
                'type' => 'table',
                'keyFld' => 'id',
                'fields' => ['id' => [], 'user_id' => [], 'qty' => []],
                'mandatoryAssign' => ['user_id' => '{{userId}}'],
            ],
        ]);
        $attrs = ['qty' => 3, 'user_id' => 99];
        AccessControl::applyMandatoryAssign($attrs, 'orders', $dm, [], (object) ['userId' => 5]);
        $this->assertSame('5', $attrs['user_id']);
        $this->assertSame(3, $attrs['qty']);
    }
}
