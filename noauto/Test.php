<?php

use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\FormLib;
use COREPOS\pos\plugins\Paycards\card\CardReader;
use COREPOS\pos\plugins\Paycards\card\CardValidator;
use COREPOS\pos\plugins\Paycards\card\EncBlock;
use COREPOS\pos\plugins\Paycards\sql\PaycardRequest;
use COREPOS\pos\plugins\Paycards\sql\PaycardGiftRequest;
use COREPOS\pos\plugins\Paycards\sql\PaycardVoidRequest;
use COREPOS\pos\plugins\Paycards\sql\PaycardResponse;
use COREPOS\pos\plugins\Paycards\xml\BetterXmlData;
use COREPOS\pos\plugins\Paycards\xml\XmlData;

class Test extends PHPUnit_Framework_TestCase
{
    public function testPlugin()
    {
        $obj = new Paycards();
        $obj->plugin_transaction_reset();
    }

    public function testModules()
    {
        $bm = new BasicCCModule();
        $this->assertEquals(false, $bm->handlesType(PaycardLib::PAYCARD_TYPE_ENCRYPTED));
        $this->assertInternalType('array', $bm->entered(true, array()));
        $bm->cleanup(array());
        $this->assertEquals(0, $bm->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        $this->assertEquals(false, $bm->handleResponse(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertInternalType('array', $bm->paycardVoid(1));
        $this->assertEquals(false, $bm->myRefNum('1-1-1'));
        $this->assertInternalType('array', $bm->lookupTransaction('1-1-1', true, 'lookup'));
        $this->assertInternalType('string', $bm->refnum(1));
        $this->assertEquals('', $bm->refnum(9999));
        $errors = array(
            PaycardLib::PAYCARD_ERR_NOSEND,
            PaycardLib::PAYCARD_ERR_COMM,
            PaycardLib::PAYCARD_ERR_TIMEOUT,
            PaycardLib::PAYCARD_ERR_DATA,
            PaycardLib::PAYCARD_ERR_PROC,
        );
        foreach ($errors as $error) {
            $this->assertEquals($error, $bm->setErrorMsg($error));
        }

        $httpErr = array(
            'curlErr' => CURLE_OK,
            'curlErrText' => '',
            'curlTime' => 0,
            'curlHTTP' => 0,
            'response' => '',
        );
        $req = new PaycardRequest('1-1-1', Database::tDataConnect());
        $req->last_paycard_transaction_id=1;

        $a = new AuthorizeDotNet();
        $this->assertEquals(true, $a->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(false, $a->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $a->entered(true, array()));
        $this->assertInternalType('array', $a->paycardVoid(1));
        $a->last_request = $req;
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $a->handleResponse($httpErr));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/adn.auth.approved.xml'));
        $a->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $a->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        $a->cleanup(array());
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/adn.auth.declined.xml'));
        $a->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $a->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/adn.auth.error.xml'));
        $a->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $a->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_COMM, $a->handleResponse($httpErr));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/adn.auth.approved.xml'));
        $a->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_NOSEND, $a->doSend(PaycardLib::PAYCARD_MODE_VOID));
        $a->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        SQLManager::addResult(array('xTransactionID'=>1));
        CoreLocal::set('paycard_trans', '1-1-1');
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $a->doSend(PaycardLib::PAYCARD_MODE_VOID));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/adn.auth.declined.xml'));
        $a->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        SQLManager::addResult(array('xTransactionID'=>1));
        CoreLocal::set('paycard_trans', '1-1-1');
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $a->doSend(PaycardLib::PAYCARD_MODE_VOID));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/adn.auth.error.xml'));
        $a->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        SQLManager::addResult(array('xTransactionID'=>1));
        CoreLocal::set('paycard_trans', '1-1-1');
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $a->doSend(PaycardLib::PAYCARD_MODE_VOID));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $a->cleanup(array());
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        $this->assertEquals(null, $a->handleResponse(array()));
        $this->assertEquals(0, $a->doSend(PaycardLib::PAYCARD_MODE_BALANCE));

        $f = new FirstData();
        $this->assertEquals(true, $f->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(false, $f->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $f->entered(true, array()));
        $this->assertInternalType('array', $f->paycardVoid(1));
        $f->last_request = $req;
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $f->handleResponse($httpErr));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/fd.auth.approved.xml'));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $f->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/fd.auth.declined.xml'));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $f->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/fd.auth.error.xml'));
        CoreLocal::set('training', 1);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $f->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        CoreLocal::set('training', '');
        $f->cleanup(array());
        try {
            CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
            $f->cleanup(array());
            $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $f->handleResponse($httpErr));
        } catch (Exception $ex){}
        try {
            $f->doSend(PaycardLib::PAYCARD_MODE_VOID);
        } catch (Exception $ex){}
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        $this->assertEquals(null, $f->handleResponse(array()));
        $this->assertEquals(0, $f->doSend(PaycardLib::PAYCARD_MODE_BALANCE));

        $g = new GoEMerchant();
        $this->assertEquals(true, $g->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(false, $g->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $g->entered(true, array()));
        $this->assertInternalType('array', $g->paycardVoid(1));
        $g->last_request = $req;
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $g->handleResponse($httpErr));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/gem.auth.approved.xml'));
        $g->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $g->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        $g->cleanup(array());
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/gem.auth.declined.xml'));
        $g->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $g->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/gem.auth.error.xml'));
        $g->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        CoreLocal::set('training', 1);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $g->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        CoreLocal::set('training', '');
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $g->handleResponse($httpErr));
        $g->cleanup(array());
        SQLManager::clear();
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/gem.auth.approved.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        CoreLocal::set('paycard_trans', '1-1-1');
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $g->doSend(PaycardLib::PAYCARD_MODE_VOID));
        SQLManager::clear();
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/gem.auth.declined.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        CoreLocal::set('paycard_trans', '1-1-1');
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $g->doSend(PaycardLib::PAYCARD_MODE_VOID));
        SQLManager::clear();
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/gem.auth.error.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        CoreLocal::set('paycard_trans', '1-1-1');
        CoreLocal::set('training', 1);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $g->doSend(PaycardLib::PAYCARD_MODE_VOID));
        CoreLocal::set('training', '');
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        $this->assertEquals(null, $g->handleResponse(array()));
        $this->assertEquals(0, $g->doSend(PaycardLib::PAYCARD_MODE_BALANCE));

        $m = new MercuryGift();
        $this->assertEquals(0, $m->doSend(-999));
        $this->assertEquals(false, $m->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(true, $m->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $m->entered(true, array()));
        $this->assertInternalType('array', $m->paycardVoid(1));
        $m->last_request = $req;
        CoreLocal::set('CCintegrate', 1);
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertInternalType('array', $m->entered(true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertInternalType('array', $m->entered(false, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_ACTIVATE);
        $this->assertInternalType('array', $m->entered(false, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        $this->assertInternalType('array', $m->entered(false, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->handleResponse($httpErr));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.approved.xml'));
        $m->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        CoreLocal::set('paycard_amount', -1);
        CoreLocal::set('training', 1);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        CoreLocal::set('training', '');
        CoreLocal::set('paycard_amount', 1);
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.declined.xml'));
        $m->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.error.xml'));
        $m->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        CoreLocal::set('paycard_response', array('Balance'=>10));
        $m->cleanup(array());
        $this->assertInternalType('array', $m->entered(true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_NOSEND, $m->handleResponse($httpErr));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.approved.xml'));
        $m->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_NOSEND, $m->doSend(PaycardLib::PAYCARD_MODE_VOID));
        SQLManager::clear();
        SQLManager::addResult(array('xAuthorizationCode'=>1));
        SQLManager::addResult(array('mode'=>'tender'));
        CoreLocal::set('paycard_trans', '1-1-1');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.approved.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->doSend(PaycardLib::PAYCARD_MODE_VOID));
        SQLManager::clear();
        SQLManager::addResult(array('xAuthorizationCode'=>1));
        SQLManager::addResult(array('mode'=>'refund'));
        CoreLocal::set('paycard_trans', '1-1-1');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.approved.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->doSend(PaycardLib::PAYCARD_MODE_VOID));
        SQLManager::clear();
        SQLManager::addResult(array('xAuthorizationCode'=>1));
        SQLManager::addResult(array('mode'=>'addvalue'));
        CoreLocal::set('paycard_trans', '1-1-1');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.declined.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->doSend(PaycardLib::PAYCARD_MODE_VOID));
        SQLManager::clear();
        SQLManager::addResult(array('xAuthorizationCode'=>1));
        SQLManager::addResult(array('mode'=>'activate'));
        CoreLocal::set('paycard_trans', '1-1-1');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.error.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        CoreLocal::set('training', 1);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->doSend(PaycardLib::PAYCARD_MODE_VOID));
        CoreLocal::set('training', '');
        CoreLocal::set('paycard_response', array('Balance'=>10));
        $m->cleanup(array());
        $this->assertInternalType('array', $m->entered(true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $m->handleResponse($httpErr));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.approved.xml'));
        $m->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $m->doSend(PaycardLib::PAYCARD_MODE_BALANCE));
        CoreLocal::set('paycard_response', array('Balance'=>10));
        $m->cleanup(array());
        $this->assertInternalType('array', $m->entered(true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_ACTIVATE);
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.approved.xml'));
        $m->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->doSend(PaycardLib::PAYCARD_MODE_ACTIVATE));
        CoreLocal::set('paycard_response', array('Balance'=>10));
        $m->cleanup(array());
        $this->assertInternalType('array', $m->entered(true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_ADDVALUE);
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.approved.xml'));
        $m->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'12345', 'tr2'=>'12345', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->doSend(PaycardLib::PAYCARD_MODE_ADDVALUE));
        CoreLocal::set('CCintegrate', '');

        $v = new Valutec();
        $this->assertEquals(false, $v->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(true, $v->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $v->entered(true, array()));
        $this->assertInternalType('array', $v->paycardVoid(1));
        CoreLocal::set('CCintegrate', 1);
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertInternalType('array', $v->entered(true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertInternalType('array', $v->entered(false, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertInternalType('array', $v->entered(false, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_ACTIVATE);
        $this->assertInternalType('array', $v->entered(false, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        $this->assertInternalType('array', $v->entered(false, array()));
        $v->last_request = $req;
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->handleResponse($httpErr));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/val.auth.approved.xml'));
        $v->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        $v->cleanup(array());
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->handleResponse($httpErr));
        $v->cleanup(array());
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/val.auth.approved.xml'));
        $v->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_NOSEND, $v->doSend(PaycardLib::PAYCARD_MODE_VOID));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->handleResponse($httpErr));
        SQLManager::clear();
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>'Debit_Sale','cardType'=>1));
        CoreLocal::set('paycard_trans', '1-1-1');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/val.auth.approved.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->doSend(PaycardLib::PAYCARD_MODE_VOID));
        $v->cleanup(array());
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/val.auth.approved.xml'));
        $v->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->doSend(PaycardLib::PAYCARD_MODE_BALANCE));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_ACTIVATE);
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/val.auth.approved.xml'));
        $m->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->doSend(PaycardLib::PAYCARD_MODE_BALANCE));
        CoreLocal::set('paycard_response', array('Balance'=>1));
        $v->cleanup(array());
        CoreLocal::set('training', 1);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->doSend(PaycardLib::PAYCARD_MODE_ACTIVATE));
        CoreLocal::set('training', '');
        $v->cleanup(array());
        $this->assertInternalType('array', $v->entered(true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_ADDVALUE);
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/val.auth.approved.xml'));
        $v->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'12345', 'tr2'=>'12345', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->doSend(PaycardLib::PAYCARD_MODE_ADDVALUE));

        $v = new MercuryE2E();
        $this->assertEquals(false, $v->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(true, $v->handlesType(PaycardLib::PAYCARD_TYPE_ENCRYPTED));
        $this->assertInternalType('array', $v->entered(true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertInternalType('array', $v->entered(true, array()));
        $this->assertInternalType('array', $v->paycardVoid(1));
        $v->last_request = $req;
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->handleResponse($httpErr));
        foreach (array('DEBIT', 'EBTFOOD', 'EBTCASH', 'CREDIT') as $type) {
            CoreLocal::set('CacheCardType', $type);
            BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/me.auth.approved.xml'));
            $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->doSend(PaycardLib::PAYCARD_MODE_AUTH));
            $v->cleanup(array());
        }
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/me.auth.declined.xml'));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        CoreLocal::set('CacheCardType', 'EBTFOOD');
        CoreLocal::set('paycard_voiceauthcode', 1);
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/me.auth.declined.xml'));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_NSF_RETRY, $v->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        CoreLocal::set('paycard_voiceauthcode', '');
        CoreLocal::set('ebt_authcode', 1);
        CoreLocal::set('ebt_vnum', 1);
        CoreLocal::set('CacheCardType', 'CREDIT');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/me.auth.error.xml'));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        CoreLocal::set('ebt_authcode', '');
        CoreLocal::set('ebt_vnum', '');
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->handleResponse($httpErr));
        $v->cleanup(array());
        $this->assertEquals(PaycardLib::PAYCARD_ERR_NOSEND, $v->doSend(PaycardLib::PAYCARD_MODE_VOID));
        SQLManager::clear();
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>1,'cardType'=>1));
        CoreLocal::set('paycard_trans', '1-1-1');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/me.auth.approved.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->doSend(PaycardLib::PAYCARD_MODE_VOID));
        SQLManager::clear();
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>1,'cardType'=>1));
        CoreLocal::set('paycard_trans', '1-1-1');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/me.auth.declined.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_NOSEND, $v->doSend(PaycardLib::PAYCARD_MODE_VOID));
        SQLManager::clear();
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>1,'cardType'=>1));
        CoreLocal::set('paycard_trans', '1-1-1');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/me.auth.error.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->doSend(PaycardLib::PAYCARD_MODE_VOID));

        $d = new MercuryDC();
        $this->assertInternalType('string', $d->prepareDataCapAuth('CREDIT', 1, false));
        $this->assertInternalType('string', $d->prepareDataCapAuth('DEBIT', 1, false));
        $this->assertInternalType('string', $d->prepareDataCapAuth('EBTFOOD', 1, true));
        $this->assertInternalType('string', $d->prepareDataCapAuth('EBTCASH', 1, false));
        $this->assertInternalType('string', $d->prepareDataCapAuth('GIFT', 1, false));
        $this->assertNotEquals(false, strstr($d->prepareDataCapAuth('EMV', 1, true), '127.0.0.1'));
        CoreLocal::set('PaycardsDatacapMode', 2);
        CoreLocal::set('PaycardsDatacapLanHost', '127.1.1.1; 127.1.1.2, 127.1.1.3 127.1.1.4');
        $this->assertNotEquals(false, strstr($d->prepareDataCapAuth('EMV', 1, true), '127.1.1.1,'));
        CoreLocal::set('PaycardsDatacapMode', 3);
        $this->assertInternalType('string', $d->prepareDataCapAuth('EMV', 1, true));
        CoreLocal::set('PaycardsDatacapMode', '');
        CoreLocal::set('ebt_authcode', 1);
        CoreLocal::set('ebt_vnum', 1);
        $this->assertInternalType('string', $d->prepareDataCapAuth('EBTFOOD', 1, true));
        CoreLocal::set('ebt_authcode', '');
        CoreLocal::set('ebt_vnum', '');
        SQLManager::clear();
        $this->assertEquals('Error', $d->prepareDataCapVoid(1));
        SQLManager::addResult(array('registerNo'=>1,'transNo'=>1));
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>'EMVSale','cardType'=>'Credit'));
        CoreLocal::set('PaycardsDatacapMode', 2);
        $this->assertInternalType('string', $d->prepareDataCapVoid(1));
        SQLManager::clear();
        SQLManager::addResult(array('registerNo'=>1,'transNo'=>1));
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>'EMVReturn','cardType'=>'Credit'));
        CoreLocal::set('PaycardsDatacapMode', 3);
        $this->assertInternalType('string', $d->prepareDataCapVoid(1));
        CoreLocal::set('PaycardsDatacapMode', '');
        SQLManager::clear();
        SQLManager::addResult(array('registerNo'=>1,'transNo'=>1));
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>'NoNSFSale','cardType'=>'Credit'));
        $this->assertInternalType('string', $d->prepareDataCapVoid(1));
        SQLManager::clear();
        SQLManager::addResult(array('registerNo'=>1,'transNo'=>1));
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>'Sale','cardType'=>'Credit'));
        $this->assertInternalType('string', $d->prepareDataCapVoid(1));
        SQLManager::clear();
        SQLManager::addResult(array('registerNo'=>1,'transNo'=>1));
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>'Sale','cardType'=>'Debit'));
        $this->assertInternalType('string', $d->prepareDataCapVoid(1));
        SQLManager::clear();
        SQLManager::addResult(array('registerNo'=>1,'transNo'=>1));
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>'Sale','cardType'=>'EBTFOOD'));
        $this->assertInternalType('string', $d->prepareDataCapVoid(1));
        $this->assertInternalType('string', $d->prepareDataCapBalance('EBTFOOD', false));
        $this->assertInternalType('string', $d->prepareDataCapBalance('EBTCASH', true));
        CoreLocal::set('training', 1);
        $this->assertInternalType('string', $d->prepareDataCapBalance('GIFT', false));
        $this->assertInternalType('string', $d->prepareDataCapGift(PaycardLib::PAYCARD_MODE_ADDVALUE, 10, false));
        CoreLocal::set('training', '');
        $this->assertInternalType('string', $d->prepareDataCapGift(PaycardLib::PAYCARD_MODE_ADDVALUE, 10, true));

        $xml = file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml');
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $d->handleResponseDataCap(str_replace('MockTC','EMV', $xml)));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $d->handleResponseDataCap(str_replace('MockCT','Foodstamp', $xml)));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $d->handleResponseDataCap(str_replace('MockCT','Cash', $xml)));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $d->handleResponseDataCap(str_replace('MockTT','PrePaid', $xml)));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $d->handleResponseDataCapBalance($xml));
        $xml = file_get_contents(__DIR__ . '/responses/dc.auth.declined.xml');
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $d->handleResponseDataCap($xml));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $d->handleResponseDataCapBalance($xml));
        $xml = file_get_contents(__DIR__ . '/responses/dc.auth.error.xml');
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $d->handleResponseDataCap($xml));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $d->handleResponseDataCapBalance($xml));
        $xml = file_get_contents(__DIR__ . '/responses/dc.auth.invalid.xml');
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $d->handleResponseDataCap($xml));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $d->handleResponseDataCapBalance($xml));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        $this->assertEquals(null, $v->handleResponse(array()));
        $this->assertEquals(0, $v->doSend(PaycardLib::PAYCARD_MODE_BALANCE));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        $v->cleanup(array());
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_ADDVALUE);
        $v->cleanup(array());

        $pmod = new PaycardModule();
        $pmod->setDialogs(new PaycardDialogs());
        $this->assertEquals(true, $pmod->isReturn('refund'));
        $this->assertEquals(false, $pmod->isReturn('foo'));
        CoreLocal::set('CCintegrate', 1);
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        $this->assertInternalType('array', $pmod->ccEntered('4111111111111111', true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertInternalType('array', $pmod->ccEntered('4111111111111111', true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertInternalType('array', $pmod->ccEntered('4111111111111111', false, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertInternalType('array', $pmod->ccEntered('4111111111111111', true, array()));
        $this->assertEquals(true, $pmod->commError(array('curlErr'=>CURLE_OK, 'curlHTTP'=>500)));
        CoreLocal::set('CCintegrate', '');
        $req = array('mode'=>PaycardLib::PAYCARD_MODE_AUTH, 'amount'=>1, 'name'=>'Foo');
        $this->assertInternalType('array', $pmod->setupVoid($req, '1-1-1', 1, array()));
    }

    public function testLib()
    {
        $reader = new CardReader();

        $this->assertEquals(PaycardLib::PAYCARD_TYPE_CREDIT, $reader->type('4111111111111111'));

        // from: http://www.gae.ucm.es/~padilla/extrawork/magexam1.html
        $stripe = '%B1234567890123445^PADILLA/L.                ^99011200000000000000**XXX******?'
            . ';1234567890123445=99011200XXXX00000000?'
            . ';011234567890123445=724724100000000000030300XXXX040400099010=************************==1=0000000000000000?';
        $this->assertInternalType('array', $reader->magstripe($stripe));
        $tr2 = ';1234567890123445=99011200XXXX00000000?';
        $this->assertInternalType('array', $reader->magstripe($tr2));
        $reader->cardInfo('02E60080asaf');
        $reader->cardInfo('02***03');
        $reader->cardInfo('6008900'. str_repeat('0', 11));
        $reader->cardInfo('6008750'. str_repeat('0', 11));

        $this->assertEquals(1, $reader->accepted('6008750'. str_repeat('0', 11)));

        $this->assertEquals('4111********1111', $reader->maskPAN('4111111111111111', 4, 4));

        CoreLocal::set('CacheCardType', 'EBTFOOD');
        CoreLocal::set('paycard_amount', 1);
        CoreLocal::set('fsEligible', -1);
        $validator = new CardValidator();
        $conf = new PaycardConf();
        $this->assertEquals(array(false, 'Enter a negative amount'), $validator->validateAmount($conf));
        CoreLocal::set('paycard_amount', -1);
        CoreLocal::set('fsEligible', 1);
        $this->assertEquals(array(false, 'Enter a positive amount'), $validator->validateAmount($conf));
        CoreLocal::set('paycard_amount', 5);
        $this->assertEquals(array(false, 'Cannot exceed amount due'), $validator->validateAmount($conf));
        CoreLocal::set('CacheCardType', 'DEBIT');
        CoreLocal::set('fsEligible', '');
        CoreLocal::set('amtdue', 1);
        CoreLocal::set('CacheCardCashBack', 1);
        $this->assertEquals(array(false, 'Cannot exceed amount due plus cashback'), $validator->validateAmount($conf));
        CoreLocal::set('CacheCardCashBack', '');
        CoreLocal::set('paycard_amount', 1);
        CoreLocal::set('PaycardRetryBalanceLimit', 0.50);
        $this->assertEquals(array(false, 'Cannot exceed card balance'), $validator->validateAmount($conf));
        CoreLocal::set('paycard_amount', '');
        CoreLocal::set('PaycardRetryBalanceLimit', '');
        CoreLocal::set('CacheCardType', '');

        $tInfo = new PaycardTenders(new PaycardConf());
        $this->assertInternalType('array', $tInfo->getTenderInfo('EMV', 'Visa'));
        SQLManager::addResult(array('TenderCode'=>'TC', 'TenderName'=>'Foo'));
        $this->assertInternalType('array', $tInfo->getTenderInfo('GIFT', 'Visa'));
        SQLManager::clear();

        CoreLocal::set('paycard_amount', 'foo');
        $bad = $validator->validateAmount($conf);
        $this->assertEquals(false, $bad[0]);
        CoreLocal::set('paycard_amount', '');

        $overrides = array(
            'Visa' => 'Visa',
            'MC' => 'MasterCard',
            'Discover' => 'Discover',
            'Amex' => 'American Express',
        );
        foreach ($overrides as $abbr => $issuer) {
            $code = substr(strtoupper($abbr), 0, 2);
            CoreLocal::set('PaycardsTenderCode' . $abbr, $code);
            $info = $tInfo->getTenderInfo('CREDIT', $issuer);
            CoreLocal::set('PaycardsTenderCode' . $abbr, '');
        }
    }

    public function testLookups()
    {
        $m = new MercuryE2E();
        $ref = str_repeat('9', 16);
        $this->assertEquals(true, $m->myRefNum($ref));
        $this->assertEquals(false, $m->myRefNum('foo'));
        $m->lookupTransaction($ref, true, 'verify');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/me.lookup.approved.xml'));
        $this->assertInternalType('array', $m->lookupTransaction($ref, true, 'verify'));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/me.lookup.declined.xml'));
        $this->assertInternalType('array', $m->lookupTransaction($ref, true, 'verify'));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/me.lookup.error.xml'));
        $this->assertInternalType('array', $m->lookupTransaction($ref, true, 'verify'));

        $g = new GoEMerchant();
        $ref = str_repeat('9', 12) . '-' . str_repeat('9', 12);
        $this->assertEquals(true, $g->myRefNum($ref));
        $this->assertEquals(false, $g->myRefNum('foo'));
        $g->lookupTransaction($ref, true, 'verify');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/gem.lookup.approved.xml'));
        $this->assertInternalType('array', $g->lookupTransaction($ref, true, 'verify'));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/gem.lookup.declined.xml'));
        $this->assertInternalType('array', $g->lookupTransaction($ref, true, 'verify'));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/gem.lookup.error.xml'));
        $this->assertInternalType('array', $g->lookupTransaction($ref, true, 'verify'));
    }

    public function testPages()
    {
        SQLManager::clear();
        CoreLocal::set('paycard_amount', 1);
        $session = new CLWrapper();
        $form = null;

        $pages = array(
            'PaycardEmvBalance',
            'PaycardEmvCaAdmin',
            'PaycardEmvGift',
            'PaycardEmvMenu',
            'PaycardEmvPage',
            'PaycardEmvSuccess',
            'PaycardEmvVoid',
            'PaycardTransListPage',
            'PaycardTransLookupPage',
            'paycardSuccess',
            'paycardboxMsgAuth',
            'paycardboxMsgBalance',
            'paycardboxMsgGift',
            'paycardboxMsgVoid',
        );
        foreach ($pages as $class) {
            $p = new $class($session, $form);
            $p->preprocess();
            ob_start();
            $p->head_content();
            $p->body_content();
            ob_end_clean();
        }

        $page = new PaycardProcessPage($session, $form);
        $this->assertInternalType('string', $page->getHeader());
        $this->assertInternalType('string', $page->getFooter());

        $page = new PaycardEmvMenu($session, $form);
        CoreLocal::set('PaycardsDatacapMode', 1);
        $this->assertEquals(true, $page->preprocess());
        CoreLocal::set('PaycardsDatacapMode', 2);
        $this->assertEquals(true, $page->preprocess());
        foreach (array('CAADMIN', 'CC', 'PVEF', 'ACGD') as $choice) {
            FormLib::set('selectlist', $choice);
            $this->assertEquals(false, $page->preprocess());
        }
        FormLib::set('selectlist', 'EBT');
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('selectlist', 'GIFT');
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('selectlist', 'CL');
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('clear-to-home', 1);
        $this->assertEquals(false, $page->preprocess());
        CoreLocal::set('PaycardsDatacapMode', '');
        FormLib::clear();

        $page = new paycardboxMsgAuth($session, $form);
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', '100');
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('reginput', '');
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('validate', '1');
        ob_start();
        $this->assertEquals(false, $page->preprocess());
        ob_end_clean();
        CoreLocal::set('amtdue', 1);
        CoreLocal::set('paycard_amount', 1);
        CoreLocal::set('PaycardRetryBalanceLimit', 1);
        CoreLocal::set('CacheCardType', 'CREDIT');
        ob_start();
        $this->assertEquals(false, $page->preprocess());
        $page->body_content();
        CoreLocal::set('CacheCardType', '');
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_GIFT);
        $page->body_content();
        CoreLocal::set('paycard_type', '');
        CoreLocal::set('paycard_amount', 'foo');
        $page->body_content();
        CoreLocal::set('PaycardRetryBalanceLimit', '');
        CoreLocal::set('amtdue', 1);
        CoreLocal::set('paycard_amount', 1);
        $page->body_content();
        CoreLocal::set('CacheCardType', 'EBTFOOD');
        CoreLocal::set('fsEligible', 1);
        CoreLocal::set('subtotal', 1);
        CoreLocal::set('amtdue', 1);
        CoreLocal::set('paycard_amount', 1);
        $page->body_content();
        CoreLocal::set('CacheCardType', '');
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_GIFT);
        CoreLocal::set('amtdue', 1);
        CoreLocal::set('paycard_amount', 1);
        $page->body_content();
        CoreLocal::set('amtdue', -1);
        CoreLocal::set('paycard_amount', -1);
        $page->body_content();
        ob_end_clean();
        CoreLocal::set('fsEligible', '');
        CoreLocal::set('subtotal', '');
        CoreLocal::set('amtdue', '');
        CoreLocal::set('paycard_amount', '');
        CoreLocal::set('paycard_type', '');
 
        FormLib::clear();

        $page = new paycardSuccess($session, $form);
        CoreLocal::set('boxMsg', '');
        $this->assertEquals(true, $page->preprocess());
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_ENCRYPTED);
        FormLib::set('reginput', 'VD');
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', '');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('doCapture', 1);
        $this->assertEquals(true, $page->preprocess());
        $temp_file = tempnam(sys_get_temp_dir(), 'Tux');
        file_put_contents($temp_file, 'mock');
        FormLib::set('bmpfile', $temp_file);
        $this->assertEquals(false, $page->preprocess());
        $this->assertEquals(false, file_exists($temp_file));
        CoreLocal::set('paycard_amount', -1);
        CoreLocal::set('PaycardsSigCapture', 1);
        ob_start();
        FormLib::set('receipt', 'ccSlip');
        $page->body_content();
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_GIFT);
        $page->body_content();
        CoreLocal::set('PaycardsSigCapture', '');
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_ENCRYPTED);
        $page->body_content();
        ob_end_clean();
        CoreLocal::set('paycard_amount', '');
        CoreLocal::set('PaycardsSigCapture', '');
        CoreLocal::set('paycard_mode', '');
        FormLib::clear();

        $page = new PaycardEmvSuccess($session, $form);
        CoreLocal::set('boxMsg', '');
        $this->assertEquals(true, $page->preprocess());
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_ENCRYPTED);
        FormLib::set('reginput', 'VD');
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', '');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('doCapture', 1);
        $this->assertEquals(true, $page->preprocess());
        $temp_file = tempnam(sys_get_temp_dir(), 'Tux');
        file_put_contents($temp_file, 'mock');
        FormLib::set('bmpfile', $temp_file);
        $this->assertEquals(false, $page->preprocess());
        $this->assertEquals(false, file_exists($temp_file));
        CoreLocal::set('paycard_amount', -1);
        CoreLocal::set('PaycardsSigCapture', 1);
        ob_start();
        FormLib::set('receipt', 'ccSlip');
        $page->body_content();
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_GIFT);
        $page->body_content();
        CoreLocal::set('PaycardsSigCapture', '');
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_ENCRYPTED);
        $page->body_content();
        ob_end_clean();
        CoreLocal::set('paycard_amount', '');
        CoreLocal::set('PaycardsSigCapture', '');
        CoreLocal::set('paycard_mode', '');
        FormLib::clear();
 
        $page = new PaycardEmvCaAdmin($session, $form);
        FormLib::set('selectlist', 'KC');
        $this->assertEquals(true, $page->preprocess());
        ob_start();
        $page->head_content();
        ob_end_clean();
        FormLib::set('selectlist', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::clear();
        FormLib::set('xml-resp', file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml'));
        FormLib::set('output-method', 'display');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('output-method', 'receipt');
        $this->assertEquals(false, $page->preprocess());
        FormLib::clear();

        $page = new PaycardEmvPage($session, $form);
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', '100');
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('reginput', 'MANUAL');
        $this->assertEquals(true, $page->preprocess());
        CoreLocal::set('amtdue', 1);
        CoreLocal::set('paycard_amount', 1);
        CoreLocal::set('PaycardRetryBalanceLimit', 1);
        CoreLocal::set('CacheCardType', 'CREDIT');
        $this->assertEquals(true, $page->preprocess());
        ob_start();
        $page->head_content();
        $page->body_content();
        CoreLocal::set('CacheCardType', '');
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_GIFT);
        $page->body_content();
        CoreLocal::set('paycard_type', '');
        CoreLocal::set('paycard_amount', 'foo');
        $page->body_content();
        CoreLocal::set('PaycardRetryBalanceLimit', '');
        $page->body_content();
        CoreLocal::set('CacheCardType', 'EBTFOOD');
        CoreLocal::set('fsEligible', 1);
        CoreLocal::set('subtotal', 1);
        CoreLocal::set('CacheCardType', '');
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_GIFT);
        $page->body_content();
        CoreLocal::set('amtdue', -1);
        CoreLocal::set('paycard_amount', -1);
        $page->body_content();
        ob_end_clean();
        CoreLocal::set('fsEligible', '');
        CoreLocal::set('subtotal', '');
        CoreLocal::set('amtdue', '');
        CoreLocal::set('paycard_amount', '');
        CoreLocal::set('paycard_type', '');
        FormLib::clear();
        FormLib::set('xml-resp', file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml'));
        $this->assertEquals(false, $page->preprocess());
        FormLib::clear();

        $page = new paycardboxMsgVoid($session, $form);
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        CoreLocal::set('RegisteredPaycardClasses', array('AuthorizeDotNet'));
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_CREDIT);
        $this->assertEquals(false, $page->preprocess());
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_GIFT);
        $this->assertEquals(true, $page->preprocess());
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_CREDIT);
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        FormLib::set('reginput', '1234');
        $this->assertEquals(true, $page->preprocess());
        ob_start();
        CoreLocal::set('paycard_amount', 1);
        $page->body_content();
        CoreLocal::set('paycard_amount', -1);
        $page->body_content();
        CoreLocal::set('paycard_amount', '');
        ob_end_clean();
        FormLib::clear();

        $page = new paycardboxMsgGift($session, $form);
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', '100');
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('reginput', '');
        $this->assertEquals(true, $page->preprocess());
        CoreLocal::set('paycard_amount', 0);
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_ACTIVATE);
        ob_start();
        $page->body_content();
        $str = ob_get_clean();
        $this->assertNotEquals(false, strstr($str, 'Activation Amount'));
        CoreLocal::set('paycard_amount', 10);
        ob_start();
        $page->body_content();
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_ADDVALUE);
        $page->body_content();
        CoreLocal::set('paycard_amount', 0);
        $page->body_content();
        CoreLocal::set('paycard_amount', -10);
        $page->body_content();
        ob_end_clean();
        CoreLocal::set('paycard_type', '');
        CoreLocal::set('paycard_amount', '');
        FormLib::clear();

        $page = new PaycardEmvGift($session, $form);
        FormLib::set('amount', 100);
        FormLib::set('mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('mode', PaycardLib::PAYCARD_MODE_ACTIVATE);
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', '200');
        $this->assertEquals(true, $page->preprocess());
        ob_start();
        $page->body_content();
        ob_end_clean();
        FormLib::set('reginput', 'MANUAL');
        $this->assertEquals(true, $page->preprocess());
        ob_start();
        $page->head_content();
        ob_end_clean();
        FormLib::clear();
        FormLib::set('xml-resp', file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml'));
        FormLib::set('amount', 100);
        FormLib::set('mode', PaycardLib::PAYCARD_MODE_ACTIVATE);
        $this->assertEquals(false, $page->preprocess());
        FormLib::clear();

        $page = new PaycardEmvBalance($session, $form);
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', 'MANUAL');
        $this->assertEquals(true, $page->preprocess());
        ob_start();
        $page->head_content();
        ob_end_clean();
        FormLib::clear();
        FormLib::set('xml-resp', file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml'));
        $this->assertEquals(false, $page->preprocess());
        FormLib::clear();

        $page = new  paycardboxMsgBalance($session, $form);
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', '');
        $this->assertEquals(true, $page->preprocess());
        FormLib::clear();

        $page = new PaycardEmvVoid($session, $form);
        SQLManager::addResult(array(0=>1));
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        SQLManager::addResult(array(0=>1));
        FormLib::set('reginput', '1234');
        $this->assertEquals(true, $page->preprocess());
        ob_start();
        $page->head_content();
        CoreLocal::set('paycard_amount', -1);
        $page->body_content();
        CoreLocal::set('paycard_amount', '');
        ob_end_clean();
        FormLib::clear();
        FormLib::set('xml-resp', file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml'));
        SQLManager::addResult(array(0=>1));
        $this->assertEquals(false, $page->preprocess());
        FormLib::clear();

        $page = new PaycardTransLookupPage($session, $form);
        FormLib::set('doLookup', 1);
        FormLib::set('id', str_repeat('9', 16));
        FormLib::set('local', 1);
        FormLib::set('mode', 'verify');
        ob_start();
        $this->assertEquals(false, $page->preprocess());
        CoreLocal::set('RegisteredPaycardClasses', array('MercuryE2E'));
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('local', 0);
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('id', '_l9');
        $page->body_content();
        ob_end_clean();
        FormLib::clear();

        $page = new PaycardTransListPage($session, $form);
        $page->preprocess();
        SQLManager::addResult(array('amount'=>1, 'PAN'=>'1', 'refNum'=>1));
        SQLManager::addResult(false); // end first while loop
        SQLManager::addResult(array('dt'=>date('Y-m-d H:i:s'), 'amount'=>1, 'PAN'=>'1', 'refNum'=>1, 'cashierNo'=>1,'laneNo'=>1,'transNo'=>1));
        ob_start();
        $page->body_content();
        ob_end_clean();
        SQLManager::clear();
        FormLib::set('selectlist', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('selectlist', 'Foo');
        $this->assertEquals(false, $page->preprocess());
        FormLib::clear();
    }

    public function testXml()
    {
        $xml = '<' . '?xml version="1.0"?' . '>'
            . '<Nodes><Empty/><Node>Value</Node><Foo>Bar</Foo><Foo>Baz</Foo></Nodes>';

/*
        $obj = new BetterXmlData($xml);
        $this->assertEquals('Value', $obj->query('/Nodes/Node'));
        $this->assertEquals(false, $obj->query('/Nodes/Fake'));
        $this->assertEquals(array('Bar','Baz'), $obj->query('/Nodes/Foo', true));
        $this->assertEquals("Bar\nBaz\n", $obj->query('/Nodes/Foo'));
        */

        $obj = new xmlData($xml);
        $this->assertEquals('Value', $obj->get('Node'));
        $this->assertEquals('Value', $obj->get_first('Node'));
        $this->assertEquals(false, $obj->get('Fake'));
        $this->assertEquals(false, $obj->get_first('Fake'));
        $this->assertEquals(false, $obj->get('Empty'));
        $this->assertEquals(false, $obj->get_first('Empty'));
        $this->assertEquals(true, $obj->isValid());
        $this->assertEquals(array('Bar','Baz','Baz'), $obj->get('Foo'));
        $obj->arrayDump();
    }

    public function testCaAdmin()
    {
        $dca = new DatacapCaAdmin();
        $dca->caLanguage();
        CoreLocal::set('PaycardsDatacapMode', 2);
        $dca->caLanguage();
        CoreLocal::set('PaycardsDatacapMode', 3);
        $dca->caLanguage();
        $funcs = array(
            'keyChange',
            'paramDownload',
            'keyReport',
            'statsReport',
            'declineReport',
            'paramReport',
        );
        foreach ($funcs as $func) {
            $this->assertInternalType('string', $dca->$func());
        }
        $xml = file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml');
        $this->assertInternalType('array', $dca->parseResponse($xml));
    }

    public function testParsers()
    {
        $valid = array(
            'DATACAP',
            'DATACAPEMV',
            'DATACAPCC',
            'DATACAPCCAUTO',
            'DATACAPDC',
            'DATACAPEF',
            'DATACAPEC',
            'DATACAPGD',
            'PVDATACAPGD',
            'PVDATACAPEF',
            'PVDATACAPEC',
            'ACDATACAPGD',
            'AVDATACAPGD',
        );
        $dc = new PaycardDatacapParser();
        $this->assertEquals(false, $dc->check('foo'));
        $this->assertInternalType('array', $dc->parse($valid[0]));
        CoreLocal::set('ttlflag', 1);
        CoreLocal::set('CacheCardCashBack', 10);
        foreach ($valid as $input) {
            $this->assertEquals(true, $dc->check($input));
            $this->assertInternalType('array', $dc->parse($input));
        }
        CoreLocal::set('fntlflag', 1);
            $this->assertInternalType('array', $dc->parse('DATACAPEF'));
        CoreLocal::set('fntlflag', '');

        $p = new PaycardSteering();
        $this->assertEquals(false, $p->check('foo'));
        $this->assertEquals(true, $p->check('PCLOOKUP'));
        $this->assertInternalType('array', $p->parse('PCLOOKUP'));

        $p = new paycardEntered();
        $p->doc();
        $this->assertEquals(false, $p->check('foo'));
        $this->assertEquals(true, $p->check('foo?'));
        $this->assertEquals(true, $p->check('02E6008012345'));
        $this->assertEquals(true, $p->check('02***03'));
        $this->assertEquals(true, $p->check('4111111111111111' . date('my')));
        CoreLocal::set('ttlflag', 1);
        $this->assertInternalType('array', $p->parse('4111111111111111' . date('my')));
        $this->assertInternalType('array', $p->parse('PV4111111111111111' . date('my')));
        $this->assertInternalType('array', $p->parse('AV4111111111111111' . date('my')));
        $this->assertInternalType('array', $p->parse('AC4111111111111111' . date('my')));
        try {
            CoreLocal::set('ttlflag', 0);
            $this->assertInternalType('array', $p->parse('AC4111111111111111' . date('my')));
        } catch (Exception $ex){}
        CoreLocal::set('ttlflag', 1);
        CoreLocal::set('amtdue', 1);
        CoreLocal::set('RegisteredPaycardClasses', array());
        $this->assertInternalType('array', $p->parse('4111111111111111' . date('my')));
        CoreLocal::set('RegisteredPaycardClasses', array('AuthorizeDotNet'));
        $this->assertInternalType('array', $p->parse('4111111111111111' . date('my')));
        $p = new paycardEntered();
        $this->assertInternalType('array', $p->parse('2E60080dummyEncrypted' . date('my')));
        CoreLocal::set('CacheCardType', 'DEBIT');
        CoreLocal::set('CacheCardCashBack', '1');
        $this->assertInternalType('array', $p->parse('2E60080dummyEncrypted' . date('my')));
        CoreLocal::set('CacheCardCashBack', '');
        CoreLocal::set('CacheCardType', 'EBTFOOD');
        try {
            $this->assertEquals(true, $p->check('2E60080dummyEncrypted' . date('my')));
            $this->assertInternalType('array', $p->parse('2E60080dummyEncrypted' . date('my')));
        } catch (Exception $ex){}
        CoreLocal::set('CacheCardType', '');
        $stripe = '%B1234567890123445^PADILLA/L.                ^99011200000000000000**XXX******?;1234567890123445=99011200XXXX00000000?';
        $this->assertEquals(true, $p->check($stripe));
        $this->assertInternalType('array', $p->parse($stripe));
    }

    public function testEnc()
    {
        $enc = new EncBlock();
        // source: Visa Test Card using Sign&Pay w/ test keys
        $pan = '02E600801F2E2700039B25423430303330302A2A2A2A2A2A363738315E544553542F4D50535E313531322A2A2A2A2A2A2A2A2A2A2A2A2A3F3B3430303330302A2A2A2A2A2A363738313D313531322A2A2A2A2A2A2A2A2A2A2A2A2A2A2A2A3FA7284186B3E8E1A3E2AD8548E732DBB5B33285117FB1B0CDBA6D732E5DF031DE3CB590DE2E02BDEF6182373B7401A3E3D304013C85D3BEFDEBF552A3C30914246B0145538F2E5856885CAA06FF64E201CB974CD506ADDCB22C9F3BF500C62310C9C88B56FD2BDF6E59481BC4B6C4F034264B2C38F8FF6F4405D563AA7D49B82221111010000000E001BFXXXX03';
        $info = $enc->parseEncBlock($pan);
        $this->assertEquals('D304013C85D3BEFDEBF552A3C30914246B0145538F2E5856885CAA06FF64E201CB974CD506ADDCB2', $info['Block']);
        $this->assertEquals('MagneSafe', $info['Format']);
        $this->assertEquals('21111010000000E001BF', $info['Key']);
        $this->assertEquals('Visa', $info['Issuer']);
        $this->assertEquals('TEST/MPS', $info['Name']);
        $this->assertEquals('6781', $info['Last4']);

        $idtech = '027901801F2F2800439B%*4003********6781^TEST/MPS^****************?*;4003********6781=********************?*D52F87101668584959DCB691AAD2222776085780319725F2281A56EAA2B44F93A63BC8D8B35DB1017D870B1E21CF6066A1FAFB4948F26010F6B7AEB8A317186DA064F37F71FF3573A4CA056242361E786DB5A2463624DF4E84968EBC368AEE8A77EE8C87AAA196FE9E0E4BB78A08C116348E92D1C02CF2A2FCEA99DE13E4C1218120510070265E060295B6F03';
        $info = $enc->parseEncBlock($idtech);
        $this->assertEquals('1FAFB4948F26010F6B7AEB8A317186DA064F37F71FF3573A4CA056242361E786DB5A2463624DF4E8', $info['Block']);
        $this->assertEquals('MagneSafe', $info['Format']);
        $this->assertEquals('120510070265E060295B', $info['Key']);
        $this->assertEquals('Unknown', $info['Issuer']);
        $this->assertEquals('TEST/MPS', $info['Name']);
        $this->assertEquals('6781', $info['Last4']);

        $magtek = '%B4003000050006781^TEST/MPS^13050000000000000?;4003000050006781=13050000000000000000?|0600|96F7CCEB8461264BB3CB3F4539163C8C59E87F2B16F1E876C778A3A15CF840422FAFF02FA2E27FD4DBC29B38535069B9|BDEC23AAA899006C36843F14E0F6A6472C8CDF81271764E160B455FC55AA5DD05F2AD04769614A91||61402200|B54A267EAAEB5B9A85212421B09BEA3B6F4AC894DBDE5A246E2780F461E63C6175C92D0F62703CAC551A206D66760744172CF7E14A223605|B01F8C4072210AA|BF6325ABD6A63EE7|9012090B01F8C4000007|F7D7||0000';
        $info = $enc->parseEncBlock($magtek);
        $this->assertEquals('BDEC23AAA899006C36843F14E0F6A6472C8CDF81271764E160B455FC55AA5DD05F2AD04769614A91', $info['Block']);
        $this->assertEquals('MagneSafe', $info['Format']);
        $this->assertEquals('9012090B01F8C4000007', $info['Key']);
        $this->assertEquals('Unknown', $info['Issuer']);
        $this->assertEquals('TEST/MPS', $info['Name']);
        $this->assertEquals('6781', $info['Last4']);

        $magtek = ';4003000050006781=13050000000000000000?|0600|96F7CCEB8461264BB3CB3F4539163C8C59E87F2B16F1E876C778A3A15CF840422FAFF02FA2E27FD4DBC29B38535069B9|BDEC23AAA899006C36843F14E0F6A6472C8CDF81271764E160B455FC55AA5DD05F2AD04769614A91||61402200|B54A267EAAEB5B9A85212421B09BEA3B6F4AC894DBDE5A246E2780F461E63C6175C92D0F62703CAC551A206D66760744172CF7E14A223605|B01F8C4072210AA|BF6325ABD6A63EE7|9012090B01F8C4000007|F7D7||0000';
        $info = $enc->parseEncBlock($magtek);
        $this->assertEquals('BDEC23AAA899006C36843F14E0F6A6472C8CDF81271764E160B455FC55AA5DD05F2AD04769614A91', $info['Block']);
        $this->assertEquals('MagneSafe', $info['Format']);
        $this->assertEquals('9012090B01F8C4000007', $info['Key']);
        $this->assertEquals('Visa', $info['Issuer']);
        $this->assertEquals('Cardholder', $info['Name']);
        $this->assertEquals('6781', $info['Last4']);

        $magtek = '%B4003000050006781^TEST/MPS^13050000000000000?|0600|96F7CCEB8461264BB3CB3F4539163C8C59E87F2B16F1E876C778A3A15CF840422FAFF02FA2E27FD4DBC29B38535069B9|BDEC23AAA899006C36843F14E0F6A6472C8CDF81271764E160B455FC55AA5DD05F2AD04769614A91||61402200|B54A267EAAEB5B9A85212421B09BEA3B6F4AC894DBDE5A246E2780F461E63C6175C92D0F62703CAC551A206D66760744172CF7E14A223605|B01F8C4072210AA|BF6325ABD6A63EE7|9012090B01F8C4000007|F7D7||0000';
        $info = $enc->parseEncBlock($magtek);
        $this->assertEquals('BDEC23AAA899006C36843F14E0F6A6472C8CDF81271764E160B455FC55AA5DD05F2AD04769614A91', $info['Block']);
        $this->assertEquals('MagneSafe', $info['Format']);
        $this->assertEquals('9012090B01F8C4000007', $info['Key']);
        $this->assertEquals('Unknown', $info['Issuer']);
        $this->assertEquals('TEST/MPS', $info['Name']);
        $this->assertEquals('6781', $info['Last4']);

        $magtekAlt = '1~FOO'
                . '|6~%B4003000050006781^TEST/MPS^13050000000000000?'
                . '|7~;4003000050006781=13050000000000000000?'
                . '|3~BLOCK'
                . '|11~KEY';
        $info = $enc->parseEncBlock($magtekAlt);
        $this->assertEquals('BLOCK', $info['Block']);
        $this->assertEquals('MagneSafe', $info['Format']);
        $this->assertEquals('KEY', $info['Key']);
        $this->assertEquals('Unknown', $info['Issuer']);
        $this->assertEquals('TEST/MPS', $info['Name']);
        $this->assertEquals('6781', $info['Last4']);

        $ingenico = '23.0%B4003000000006781^TEST/MPS^15120000000000000?@@;4003000000006781=15120000000000000000?@@956959220A1B34705735A3035B017D4B3C5DD67575DC0BFEB85A02A71E3F8C6A67160D720F37CBCE16E061D14D520EAC:21111010000002600182:320D3C963EF3A21D730A9B467C8AE43022DDC9241BB3D2FEBD936773191B55BE6F2948589ABBA829:21111010000002600183;fakeTrack3';
        $info = $enc->parseEncBlock($ingenico);
        $this->assertEquals('320D3C963EF3A21D730A9B467C8AE43022DDC9241BB3D2FEBD936773191B55BE6F2948589ABBA829', $info['Block']);
        $this->assertEquals('MagneSafe', $info['Format']);
        $this->assertEquals('21111010000002600183', $info['Key']);
        $this->assertEquals('Unknown', $info['Issuer']);
        $this->assertEquals('TEST/MPS', $info['Name']);
        $this->assertEquals('6781', $info['Last4']);

        $ingenico = '23.0;4003000000006781=15120000000000000000?@@320D3C963EF3A21D730A9B467C8AE43022DDC9241BB3D2FEBD936773191B55BE6F2948589ABBA829:21111010000002600183';
        $info = $enc->parseEncBlock($ingenico);
        $this->assertEquals('320D3C963EF3A21D730A9B467C8AE43022DDC9241BB3D2FEBD936773191B55BE6F2948589ABBA829', $info['Block']);
        $this->assertEquals('MagneSafe', $info['Format']);
        $this->assertEquals('21111010000002600183', $info['Key']);
        $this->assertEquals('Visa', $info['Issuer']);
        $this->assertEquals('Cardholder', $info['Name']);
        $this->assertEquals('6781', $info['Last4']);

        $pin = str_repeat('F', 36);
        $enc->parsePinBlock($pin);
        $pin .= '0';
        $enc->parsePinBlock($pin);
    }

    public function testNotifier()
    {
        $n = new TermStateNotifier();
        $states = array(
            'swipe',
            'ready',
            'pin',
            'type',
            'cashback',
            'DCDC',
            'DCCC',
            'DCEF',
            'DCEC',
            'invalid',
        );
        foreach ($states as $state) {
            CoreLocal::set('ccTermState', $state);
            $this->assertInternalType('string', $n->draw());
        }
        CoreLocal::set('PaycardsCashierFacing', '1');
        $this->assertInternalType('string', $n->draw());
    }

    public function testDialogs()
    {
        $d = new PaycardDialogs();
        try {
            $d->enabledCheck();
        } catch (Exception $ex) {}
        CoreLocal::set('CCintegrate', 1);
        $this->assertEquals(true, $d->enabledCheck());
        CoreLocal::set('CCintegrate', '');

        CoreLocal::set('paycard_exp', date('my'));
        $this->assertEquals(true, $d->validateCard('4111111111111111'));
        CoreLocal::set('paycard_exp', date('0101'));
        try {
            $d->validateCard('4111111111111111');
        } catch (Exception $ex) {}
        try {
            $d->validateCard('4111111111111112'); // bad luhn checksum
        } catch (Exception $ex) {}

        try {
            $d->voidableCheck('1111', array(1,1,1));
        } catch (Exception $ex) {}
        SQLManager::addResult(array('transID'=>1));
        SQLManager::addResult(array('transID'=>1));
        try {
            $d->voidableCheck('1111', array(1,1,1)); // too many results
        } catch (Exception $ex) {}
        SQLManager::clear();
        SQLManager::addResult(array('transID'=>1));
        $this->assertEquals(1, $d->voidableCheck('1111', array(1,1,1)));

        $this->assertInternalType('string', $d->invalidMode());

        try {
            $d->getRequest('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::addResult(array(0=>1));
        SQLManager::addResult(array(0=>1));
        try {
            $d->getRequest('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::clear();
        SQLManager::addResult(array(0=>1));
        $this->assertEquals(array(0=>1), $d->getRequest('1-1-1', 1));

        try {
            $d->getResponse('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::addResult(array(0=>1));
        SQLManager::addResult(array(0=>1));
        try {
            $d->getResponse('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::clear();
        SQLManager::addResult(array(0=>1));
        $this->assertEquals(array(0=>1), $d->getResponse('1-1-1', 1));

        try {
            $d->getTenderLine('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::addResult(array(0=>1));
        SQLManager::addResult(array(0=>1));
        try {
            $d->getTenderLine('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::clear();
        SQLManager::addResult(array(0=>1));
        $this->assertEquals(array(0=>1), $d->getTenderLine('1-1-1', 1));

        $this->assertEquals(true, $d->notVoided('1-1-1', 1));
        try {
            SQLManager::addResult(array(0=>1, 'transID'=>1));
            $d->notVoided('1-1-1', 1);
        } catch (Exception $ex) {}

        $response = array(
            'commErr' => 0,
            'httpCode' => 200,
            'validResponse' => 1,
            'xResponseCode' => 1,
            'xTransactionID' => 1,
        );
        $request = array('live'=>$d->paycardLive(PaycardLib::PAYCARD_TYPE_CREDIT));
        $lineitem = array(
            'trans_type'=>'T',
            'trans_subtype'=>'CC',
            'voided'=>0,
            'trans_status'=>'',
        );
        $this->assertEquals(true, $d->validateVoid($request, $response, $lineitem));

        $request['live'] = 99;
        try {
            $d->validateVoid($request, $response, $lineitem);
        } catch (Exception $ex) {}
        $request = array('live'=>$d->paycardLive(PaycardLib::PAYCARD_TYPE_CREDIT));
        $response['httpCode'] = 500;
        try {
            $d->validateVoid($request, $response, $lineitem);
        } catch (Exception $ex) {}
        $response['httpCode'] = 200;
        $response['xResponseCode'] = 2;
        try {
            $d->validateVoid($request, $response, $lineitem);
        } catch (Exception $ex) {}
        $response['xResponseCode'] = 1;
        $response['xTransactionID'] = 0;
        try {
            $d->validateVoid($request, $response, $lineitem);
        } catch (Exception $ex) {}
        $response['xTransactionID'] = 1;
        $lineitem['trans_type'] = 'I';
        try {
            $d->validateVoid($request, $response, $lineitem);
        } catch (Exception $ex) {}
        $lineitem['trans_type'] = 'T';
        try {
            $d->validateVoid($request, $response, $lineitem);
        } catch (Exception $ex) {}
        $lineitem['trans_type'] = 'T';
        $lineitem['voided'] = 1;
        try {
            $d->validateVoid($request, $response, $lineitem);
        } catch (Exception $ex) {}

        $d->paycardLive();
        CoreLocal::set('training', '');
        CoreLocal::set('CashierNo', '');
        CoreLocal::set('CCintegrate', 1);
        $this->assertEquals(1, $d->paycardLive(PaycardLib::PAYCARD_TYPE_CREDIT));
        CoreLocal::set('CCintegrate', '');
        $this->assertEquals(0, $d->paycardLive(PaycardLib::PAYCARD_TYPE_CREDIT));
    }

    public function testReqResp()
    {
        $req = new PaycardRequest('1-1-1', Database::tDataConnect());
        $req->setManual(0);
        $req->setRefNum(0);
        $req->setMode(0);
        $req->setAmount(0);
        $req->setCardholder(0);
        $req->setIssuer(0);
        $req->setPAN(0);
        $req->setSent(1, 1, 0, 0);
        $req->setProcessor(0);
        $req->saveRequest();
        $req->changeAmount(1);
        $req->updateCardInfo('pan', 'name', 'issuer');

        $resp = new PaycardResponse($req, array('curlTime'=>0, 'curlErr'=>0, 'curlHTTP'=>200), Database::tDataConnect());
        $resp->setToken(1,2,3);
        $resp->setBalance(0);
        $resp->setValid(1);
        $resp->setResponseCode(1);
        $resp->setResultCode(1);
        $resp->setNormalizedCode(1);
        $resp->setResultMsg('asdf');
        $resp->setApprovalNum('asdf');
        $resp->setTransactionID('asdf');
        $resp->saveResponse();

        CoreLocal::set('paycard_trans', '1-1-1');
        $req = new PaycardVoidRequest('1-1-1', Database::tDataConnect());
        try {
            $req->findOriginal();
        } catch (Exception $ex){}
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'xToken'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>1,'cardType'=>1));
        $req->findOriginal();
        SQLManager::clear();
        $req->saveRequest();

        $req = new PaycardGiftRequest('1-1-1', Database::tDataConnect());
    }

    public function testAjax()
    {
        $ajax = new AjaxPaycardAuth();
        $json = $ajax->ajax();
        $this->assertNotEquals(false, strstr($json['main_frame'], 'boxMsg2.php'));
        CoreLocal::set('RegisteredPaycardClasses', array('AuthorizeDotNet'));
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_CREDIT);
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        CoreLocal::set('paycard_amount', 1);
        CoreLocal::set('paycard_PAN', '4111111111111111');
        CoreLocal::set('paycard_exp', date('my'));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/adn.auth.approved.xml'));
        $json = $ajax->ajax();
        $this->assertNotEquals(false, strstr($json['main_frame'], 'paycardSuccess.php'));
    }
}

