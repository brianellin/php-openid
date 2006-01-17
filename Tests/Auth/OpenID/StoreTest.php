<?php

/**
 * A test script for the OpenIDStore classes.
 *
 * PHP versions 4 and 5
 *
 * LICENSE: See the COPYING file included in this distribution.
 *
 * @package OpenID
 * @author JanRain, Inc. <openid@janrain.com>
 * @copyright 2005 Janrain, Inc.
 * @license http://www.gnu.org/copyleft/lesser.html LGPL
 */

require_once('Auth/OpenID/Association.php');
require_once('Auth/OpenID/CryptUtil.php');
require_once('Auth/OpenID/OIDUtil.php');

class Tests_Auth_OpenID_StoreTest extends PHPUnit_TestCase {

    function setUp()
    {
        global $_Auth_OpenID_letters, $_Auth_OpenID_digits,
            $_Auth_OpenID_punct;

        $this->letters = $_Auth_OpenID_letters;
        $this->digits = $_Auth_OpenID_digits;
        $this->punct = $_Auth_OpenID_punct;
        $this->allowed_nonce = $this->letters . $this->digits;
        $this->allowed_handle = $this->letters . $this->digits . $this->punct;
    }

    function generateNonce()
    {
        return Auth_OpenID_CryptUtil::randomString(8, $this->allowed_nonce);
    }

    function genAssoc($now, $issued = 0, $lifetime = 600)
    {
        $sec = call_user_func(array('Auth_OpenID_CryptUtil', 'randomString'),
                              20);
        $hdl = Auth_OpenID_CryptUtil::randomString(128, $this->allowed_handle);
        return new Auth_OpenID_Association($hdl, $sec, $now + $issued,
                                          $lifetime, 'HMAC-SHA1');
    }

    function _checkRetrieve(&$store, $url, $handle, $expected, $name=null)
    {
        $retrieved_assoc = $store->getAssociation($url, $handle);
        if (($expected === null) || ($store->isDumb())) {
            $this->assertNull($retrieved_assoc);
        } else {
            if ($retrieved_assoc === null) {
                $this->fail("$name: Got null when expecting " .
                            $expected->serialize());
            } else {
                $this->assertEquals($retrieved_assoc->serialize(),
                                    $expected->serialize(), $name);
            }
        }
    }

    function _checkRemove(&$store, $url, $handle, $expected)
    {
        $present = $store->removeAssociation($url, $handle);
        $expectedPresent = (!$store->isDumb() && $expected);
        $this->assertTrue((!$expectedPresent && !$present) ||
                          ($expectedPresent && $present));
    }

    /**
     * Make sure a given store has a minimum of API compliance. Call
     * this function with an empty store.
     *
     * Raises AssertionError if the store does not work as expected.
     *
     * OpenIDStore -> NoneType
     */
    function _testStore($store)
    {

        // Association functions
        $now = time();

        $server_url = 'http://www.myopenid.com/openid';

        $assoc = $this->genAssoc($now);

        $this->_checkRetrieve($store, $server_url, null, null,
            'Make sure that a missing association returns no result');

        $store->storeAssociation($server_url, $assoc);
        $this->_checkRetrieve($store, $server_url, null, $assoc,
            'Check that after storage, getting returns the same result');

        $this->_checkRetrieve($store, $server_url, null, $assoc,
            'more than once');

        $store->storeAssociation($server_url, $assoc);
        $this->_checkRetrieve($store, $server_url, null, $assoc,
            'Storing more than once has no ill effect');

        // Removing an association that does not exist returns not present
        $this->_checkRemove($store, $server_url, $assoc->handle . 'x', false);

        // Removing an association that does not exist returns not present
        $this->_checkRemove($store, $server_url . 'x', $assoc->handle, false);

        // Removing an association that is present returns present
        $this->_checkRemove($store, $server_url, $assoc->handle, true);

        // but not present on subsequent calls
        $this->_checkRemove($store, $server_url, $assoc->handle, false);

        // Put assoc back in the store
        $store->storeAssociation($server_url, $assoc);

        // More recent and expires after assoc
        $assoc2 = $this->genAssoc($now, $issued = 1);
        $store->storeAssociation($server_url, $assoc2);

        $this->_checkRetrieve($store, $server_url, null, $assoc2,
            'After storing an association with a different handle, but the
same $server_url, the handle with the later expiration is
returned.');

        $this->_checkRetrieve($store, $server_url, $assoc->handle, $assoc,
            'We can still retrieve the older association');

        $this->_checkRetrieve($store, $server_url, $assoc2->handle, $assoc2,
            'Plus we can retrieve the association with the later expiration
explicitly');

        // More recent, but expires earlier than assoc2 or assoc
        $assoc3 = $this->genAssoc($now, $issued = 2, $lifetime = 100);
        $store->storeAssociation($server_url, $assoc3);

        $this->_checkRetrieve($store, $server_url, null, $assoc3);
        $this->_checkRetrieve($store, $server_url, $assoc->handle, $assoc);
        $this->_checkRetrieve($store, $server_url, $assoc2->handle, $assoc2);
        $this->_checkRetrieve($store, $server_url, $assoc3->handle, $assoc3);

        $this->_checkRemove($store, $server_url, $assoc2->handle, true);

        $this->_checkRetrieve($store, $server_url, null, $assoc3);
        $this->_checkRetrieve($store, $server_url, $assoc->handle, $assoc);
        $this->_checkRetrieve($store, $server_url, $assoc2->handle, null);
        $this->_checkRetrieve($store, $server_url, $assoc3->handle, $assoc3);

        $this->_checkRemove($store, $server_url, $assoc2->handle, false);
        $this->_checkRemove($store, $server_url, $assoc3->handle, true);

        $this->_checkRetrieve($store, $server_url, null, $assoc);
        $this->_checkRetrieve($store, $server_url, $assoc->handle, $assoc);
        $this->_checkRetrieve($store, $server_url, $assoc2->handle, null);
        $this->_checkRetrieve($store, $server_url, $assoc3->handle, null);

        $this->_checkRemove($store, $server_url, $assoc2->handle, false);
        $this->_checkRemove($store, $server_url, $assoc->handle, true);
        $this->_checkRemove($store, $server_url, $assoc3->handle, false);
        $this->_checkRetrieve($store, $server_url, null, null);
        $this->_checkRetrieve($store, $server_url, $assoc->handle, null);
        $this->_checkRetrieve($store, $server_url, $assoc2->handle, null);
        $this->_checkRetrieve($store, $server_url,$assoc3->handle, null);

        $this->_checkRemove($store, $server_url, $assoc2->handle, false);
        $this->_checkRemove($store, $server_url, $assoc->handle, false);
        $this->_checkRemove($store, $server_url, $assoc3->handle, false);
    }

    function _checkUseNonce(&$store, $nonce, $expected)
    {
        $actual = $store->useNonce($nonce);
        $expected = $store->isDumb() || $expected;
        $this->assertTrue(($actual && $expected) || (!$actual && !$expected));
    }

    function _testNonce(&$store)
    {
        // Nonce functions

        // Random nonce (not in store)
        $nonce1 = $this->generateNonce();

        // A nonce is not present by default
        $this->_checkUseNonce($store, $nonce1, false);

        // Storing once causes useNonce to return true the first, and
        // only the first, time it is called after the $store->
        $store->storeNonce($nonce1);
        $this->_checkUseNonce($store, $nonce1, true);
        $this->_checkUseNonce($store, $nonce1, false);

        // Storing twice has the same effect as storing once.
        $store->storeNonce($nonce1);
        $store->storeNonce($nonce1);
        $this->_checkUseNonce($store, $nonce1, true);
        $this->_checkUseNonce($store, $nonce1, false);

        // Auth key functions

        // There is no key to start with, so generate a new key and
        // return it.
        $key = $store->getAuthKey();

        // The second time around should return the same as last time.
        $key2 = $store->getAuthKey();
        $this->assertEquals($key, $key2);
        $this->assertEquals(strlen($key), $store->AUTH_KEY_LEN);
    }

    function test_filestore()
    {
        require_once('Auth/OpenID/Store/FileStore.php');

        $temp_dir = Auth_OpenID_mkdtemp('/tmp');

        if (!$temp_dir) {
            trigger_error('Could not create temporary directory ' .
                          'with Auth_OpenID_mkdtemp', E_USER_WARNING);
            return null;
        }

        $store = new Auth_OpenID_FileStore($temp_dir);
        $this->_testStore($store);
        $this->_testNonce($store);
        $store->destroy();
    }
}

?>