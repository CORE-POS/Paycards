<?php

use COREPOS\pos\lib\FormLib;

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
        $this->assertInternalType('array', $bm->paycard_void(1));
        $this->assertEquals(false, $bm->myRefNum('1-1-1'));
        $this->assertInternalType('array', $bm->lookupTransaction('1-1-1', true, 'lookup'));
        $this->assertInternalType('string', $bm->refnum(1));
        $this->assertEquals('', $bm->refnum(9999));
        $this->assertInternalType('string', $bm->array2post(array('foo'=>'bar')));
        $soaped = $bm->soapify('action',array('foo'=>'bar'));
        $this->assertInternalType('string', $soaped);
        $this->assertInternalType('string', $bm->desoapify('action', $soaped));
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
        $req = new PaycardRequest('1-1-1');
        $req->last_paycard_transaction_id=1;

        $a = new AuthorizeDotNet();
        $this->assertEquals(true, $a->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(false, $a->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $a->entered(true, array()));
        $this->assertInternalType('array', $a->paycard_void(1));
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
        $a->cleanup(array());

        $f = new FirstData();
        $this->assertEquals(true, $f->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(false, $f->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $f->entered(true, array()));
        $this->assertInternalType('array', $f->paycard_void(1));
        $f->last_request = $req;
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $f->handleResponse($httpErr));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/fd.auth.approved.xml'));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $f->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        $f->cleanup(array());
        try {
            CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
            $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $f->handleResponse($httpErr));
        } catch (Exception $ex){}

        $g = new GoEMerchant();
        $this->assertEquals(true, $g->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(false, $g->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $g->entered(true, array()));
        $this->assertInternalType('array', $g->paycard_void(1));
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
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $g->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $g->handleResponse($httpErr));
        $g->cleanup(array());
        SQLManager::clear();
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/gem.auth.approved.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        CoreLocal::set('paycard_trans', '1-1-1');
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $g->doSend(PaycardLib::PAYCARD_MODE_VOID));

        $m = new MercuryGift();
        $this->assertEquals(0, $m->doSend(-999));
        $this->assertEquals(false, $m->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(true, $m->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $m->entered(true, array()));
        $this->assertInternalType('array', $m->paycard_void(1));
        $m->last_request = $req;
        CoreLocal::set('CCintegrate', 1);
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertInternalType('array', $m->entered(true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_ACTIVATE);
        $this->assertInternalType('array', $m->entered(true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->handleResponse($httpErr));
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/mg.auth.approved.xml'));
        $m->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        CoreLocal::set('paycard_amount', -1);
        CoreLocal::set('training', 1);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_DATA, $m->doSend(PaycardLib::PAYCARD_MODE_AUTH));
        CoreLocal::set('paycard_amount', 1);
        CoreLocal::set('training', '');
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
        $this->assertInternalType('array', $v->paycard_void(1));
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
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>1,'cardType'=>1));
        CoreLocal::set('paycard_trans', '1-1-1');
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/val.auth.approved.xml'));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->doSend(PaycardLib::PAYCARD_MODE_VOID));
        $v->cleanup(array());
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/val.auth.approved.xml'));
        $v->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->doSend(PaycardLib::PAYCARD_MODE_BALANCE));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_ACTIVATE);
        BasicCCModule::mockResponse(file_get_contents(__DIR__ . '/responses/val.auth.approved.xml'));
        $m->setPAN(array('pan'=>'4111111111111111', 'tr1'=>'', 'tr2'=>'', 'tr3'=>''));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->doSend(PaycardLib::PAYCARD_MODE_ACTIVATE));
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
        $this->assertInternalType('array', $v->paycard_void(1));
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

        $this->assertInternalType('string', $v->prepareDataCapAuth('DEBIT', 1, false));
        $this->assertInternalType('string', $v->prepareDataCapAuth('EBTFOOD', 1, true));
        $this->assertInternalType('string', $v->prepareDataCapAuth('EBTCASH', 1, false));
        $this->assertInternalType('string', $v->prepareDataCapAuth('GIFT', 1, false));
        $this->assertInternalType('string', $v->prepareDataCapAuth('EMV', 1, true));
        SQLManager::clear();
        $this->assertEquals('Error', $v->prepareDataCapVoid(1));
        SQLManager::addResult(array('registerNo'=>1,'transNo'=>1));
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'token'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>1,'cardType'=>1));
        $this->assertInternalType('string', $v->prepareDataCapVoid(1));
        $this->assertInternalType('string', $v->prepareDataCapBalance('EBTFOOD', false));
        $this->assertInternalType('string', $v->prepareDataCapBalance('EBTCASH', true));
        $this->assertInternalType('string', $v->prepareDataCapBalance('GIFT', false));
        $this->assertInternalType('string', $v->prepareDataCapGift(PaycardLib::PAYCARD_MODE_ADDVALUE, 10, false));
        $this->assertInternalType('string', $v->prepareDataCapGift(PaycardLib::PAYCARD_MODE_ADDVALUE, 10, true));

        $xml = file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml');
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->handleResponseDataCap(str_replace('MockTC','EMV', $xml)));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->handleResponseDataCap(str_replace('MockCT','Foodstamp', $xml)));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->handleResponseDataCap(str_replace('MockCT','Cash', $xml)));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->handleResponseDataCap(str_replace('MockTT','PrePaid', $xml)));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_OK, $v->handleResponseDataCapBalance($xml));
        $xml = file_get_contents(__DIR__ . '/responses/dc.auth.declined.xml');
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->handleResponseDataCap($xml));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->handleResponseDataCapBalance($xml));
        $xml = file_get_contents(__DIR__ . '/responses/dc.auth.error.xml');
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->handleResponseDataCap($xml));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_PROC, $v->handleResponseDataCapBalance($xml));

        $this->assertEquals(true, PaycardModule::isReturn('refund'));
        $this->assertEquals(false, PaycardModule::isReturn('foo'));
        CoreLocal::set('CCintegrate', 1);
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_BALANCE);
        $this->assertInternalType('array', PaycardModule::ccEntered('4111111111111111', true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertInternalType('array', PaycardModule::ccEntered('4111111111111111', true, array()));
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        $this->assertInternalType('array', PaycardModule::ccEntered('4111111111111111', true, array()));
        $this->assertEquals(true, PaycardModule::commError(array('curlErr'=>CURLE_OK, 'curlHTTP'=>500)));
        CoreLocal::set('CCintegrate', '');
    }

    public function testLib()
    {
        PaycardLib::paycard_wipe_pan();
        PaycardLib::paycard_live();
        $this->assertEquals(PaycardLib::PAYCARD_TYPE_CREDIT, PaycardLib::paycard_type('4111111111111111'));

        // from: http://www.gae.ucm.es/~padilla/extrawork/magexam1.html
        $stripe = '%B1234567890123445^PADILLA/L.                ^99011200000000000000**XXX******?'
            . ';1234567890123445=99011200XXXX00000000?'
            . ';011234567890123445=724724100000000000030300XXXX040400099010=************************==1=0000000000000000?';
        $this->assertInternalType('array', PaycardLib::paycard_magstripe($stripe));
        $tr2 = ';1234567890123445=99011200XXXX00000000?';
        $this->assertInternalType('array', PaycardLib::paycard_magstripe($tr2));
        PaycardLib::paycard_info('02E60080asaf');
        PaycardLib::paycard_info('02***03');
        PaycardLib::paycard_info('6008900'. str_repeat('0', 11));
        PaycardLib::paycard_info('6008750'. str_repeat('0', 11));

        $this->assertEquals(0, PaycardLib::paycard_accepted('6008750'. str_repeat('0', 11), false));

        CoreLocal::set('training', '');
        CoreLocal::set('CashierNo', '');
        CoreLocal::set('CCintegrate', 1);
        $this->assertEquals(1, PaycardLib::paycard_live(PaycardLib::PAYCARD_TYPE_CREDIT));
        CoreLocal::set('CCintegrate', '');
        $this->assertEquals(0, PaycardLib::paycard_live(PaycardLib::PAYCARD_TYPE_CREDIT));

        $this->assertEquals('4111********1111', PaycardLib::paycard_maskPAN('4111111111111111', 4, 4));

        CoreLocal::set('CacheCardType', 'EBTFOOD');
        CoreLocal::set('paycard_amount', 1);
        CoreLocal::set('fsEligible', -1);
        $this->assertEquals(array(false, 'Enter a negative amount'), PaycardLib::validateAmount());
        CoreLocal::set('paycard_amount', -1);
        CoreLocal::set('fsEligible', 1);
        $this->assertEquals(array(false, 'Enter a positive amount'), PaycardLib::validateAmount());
        CoreLocal::set('paycard_amount', 5);
        $this->assertEquals(array(false, 'Cannot exceed amount due'), PaycardLib::validateAmount());
        CoreLocal::set('CacheCardType', 'DEBIT');
        CoreLocal::set('fsEligible', '');
        CoreLocal::set('amtdue', 1);
        CoreLocal::set('CacheCardCashBack', 1);
        $this->assertEquals(array(false, 'Cannot exceed amount due plus cashback'), PaycardLib::validateAmount());
        CoreLocal::set('CacheCardCashBack', '');
        CoreLocal::set('paycard_amount', 1);
        CoreLocal::set('PaycardRetryBalanceLimit', 0.50);
        $this->assertEquals(array(false, 'Cannot exceed card balance'), PaycardLib::validateAmount());
        CoreLocal::set('paycard_amount', '');
        CoreLocal::set('PaycardRetryBalanceLimit', '');
        CoreLocal::set('CacheCardType', '');

        $this->assertInternalType('array', PaycardLib::getTenderInfo('EMV', 'Visa'));
        SQLManager::addResult(array('TenderCode'=>'TC', 'TenderName'=>'Foo'));
        $this->assertInternalType('array', PaycardLib::getTenderInfo('GIFT', 'Visa'));
        SQLManager::clear();
    }

    public function testLookups()
    {
        $m = new MercuryE2E();
        $ref = str_repeat('9', 16);
        $this->assertEquals(true, $m->myRefNum($ref));
        $this->assertEquals(false, $m->myRefNum('foo'));
        $m->lookupTransaction($ref, true, 'verify');

        $g = new GoEMerchant();
        $ref = str_repeat('9', 12) . '-' . str_repeat('9', 12);
        $this->assertEquals(true, $g->myRefNum($ref));
        $this->assertEquals(false, $g->myRefNum('foo'));
        $g->lookupTransaction($ref, true, 'verify');
    }

    public function testPages()
    {
        SQLManager::clear();
        CoreLocal::set('paycard_amount', 1);

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
            $p = new $class();
            $p->preprocess();
            ob_start();
            $p->head_content();
            $p->body_content();
            ob_end_clean();
        }

        $page = new PaycardProcessPage();
        $this->assertInternalType('string', $page->getHeader());
        $this->assertInternalType('string', $page->getFooter());

        $page = new PaycardEmvMenu();
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

        $page = new paycardboxMsgAuth();
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
        ob_start();
        $this->assertEquals(false, $page->preprocess());
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

        $page = new paycardSuccess();
        CoreLocal::set('boxMsg', '');
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
        $page->body_content();
        ob_end_clean();
        CoreLocal::set('paycard_amount', '');
        CoreLocal::set('PaycardsSigCapture', '');
        CoreLocal::set('paycard_mode', '');
        FormLib::clear();

        $page = new PaycardEmvSuccess();
        CoreLocal::set('boxMsg', '');
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
        $page->body_content();
        ob_end_clean();
        CoreLocal::set('paycard_amount', '');
        CoreLocal::set('PaycardsSigCapture', '');
        CoreLocal::set('paycard_mode', '');
        FormLib::clear();
 
        $page = new PaycardEmvCaAdmin();
        FormLib::set('selectlist', 'KC');
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('selectlist', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::clear();
        FormLib::set('xml-resp', file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml'));
        FormLib::set('output-method', 'display');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('output-method', 'receipt');
        $this->assertEquals(false, $page->preprocess());
        FormLib::clear();

        $page = new PaycardEmvPage();
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', '100');
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('reginput', '');
        $this->assertEquals(true, $page->preprocess());
        CoreLocal::set('amtdue', 1);
        CoreLocal::set('paycard_amount', 1);
        CoreLocal::set('PaycardRetryBalanceLimit', 1);
        $this->assertEquals(true, $page->preprocess());
        ob_start();
        $page->head_content();
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

        $page = new paycardboxMsgVoid();
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_AUTH);
        CoreLocal::set('RegisteredPaycardClasses', array('AuthorizeDotNet'));
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_TYPE_CREDIT);
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        CoreLocal::set('paycard_mode', PaycardLib::PAYCARD_MODE_VOID);
        FormLib::set('reginput', '1234');
        $this->assertEquals(true, $page->preprocess());
        FormLib::clear();

        $page = new paycardboxMsgGift();
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', '100');
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('reginput', '');
        $this->assertEquals(true, $page->preprocess());
        CoreLocal::set('paycard_amount', 0);
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_MODE_ACTIVATE);
        ob_start();
        $page->body_content();
        CoreLocal::set('paycard_amount', 10);
        $page->body_content();
        CoreLocal::set('paycard_type', PaycardLib::PAYCARD_MODE_ADDVALUE);
        $page->body_content();
        CoreLocal::set('paycard_amount', 0);
        $page->body_content();
        CoreLocal::set('paycard_amount', -10);
        $page->body_content();
        ob_end_clean();
        CoreLocal::set('paycard_type', '');
        CoreLocal::set('paycard_amount', '');
        FormLib::clear();

        $page = new PaycardEmvGift();
        FormLib::set('amount', 100);
        FormLib::set('mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('mode', PaycardLib::PAYCARD_MODE_ACTIVATE);
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', '200');
        $this->assertEquals(true, $page->preprocess());
        FormLib::set('reginput', 'MANUAL');
        $this->assertEquals(true, $page->preprocess());
        ob_start();
        $page->head_content();
        ob_end_clean();
        FormLib::clear();
        FormLib::set('xml-resp', file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml'));
        FormLib::set('amount', 100);
        FormLib::set('mode', PaycardLib::PAYCARD_MODE_AUTH);
        $this->assertEquals(false, $page->preprocess());
        FormLib::clear();

        $page = new PaycardEmvBalance();
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

        $page = new  paycardboxMsgBalance();
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('reginput', '');
        $this->assertEquals(true, $page->preprocess());
        FormLib::clear();

        $page = new PaycardEmvVoid();
        SQLManager::addResult(array(0=>1));
        FormLib::set('reginput', 'CL');
        $this->assertEquals(false, $page->preprocess());
        SQLManager::addResult(array(0=>1));
        FormLib::set('reginput', '1234');
        $this->assertEquals(true, $page->preprocess());
        ob_start();
        $page->head_content();
        ob_end_clean();
        FormLib::clear();
        FormLib::set('xml-resp', file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml'));
        SQLManager::addResult(array(0=>1));
        $this->assertEquals(false, $page->preprocess());
        FormLib::clear();

        $page = new PaycardTransLookupPage();
        FormLib::set('doLookup', 1);
        FormLib::set('id', str_repeat('9', 16));
        FormLib::set('local', 1);
        FormLib::set('mode', 'verify');
        ob_start();
        $this->assertEquals(false, $page->preprocess());
        CoreLocal::set('RegisteredPaycardClasses', array('MercuryE2E'));
        $this->assertEquals(false, $page->preprocess());
        FormLib::set('id', '9_l');
        $page->body_content();
        ob_end_clean();
        FormLib::clear();

        $page = new PaycardTransListPage();
        SQLManager::addResult(array('amount'=>1, 'PAN'=>'1', 'refNum'=>1));
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
            . '<Nodes><Node>Value</Node><Foo>Bar</Foo><Foo>Baz</Foo></Nodes>';

        $obj = new BetterXmlData($xml);
        $this->assertEquals('Value', $obj->query('/Nodes/Node'));
        $this->assertEquals(false, $obj->query('/Nodes/Fake'));
        $this->assertEquals(array('Bar','Baz'), $obj->query('/Nodes/Foo', true));
        $this->assertEquals("Bar\nBaz\n", $obj->query('/Nodes/Foo'));

        $obj = new xmlData($xml);
        $this->assertEquals('Value', $obj->get('Node'));
        $this->assertEquals('Value', $obj->get_first('Node'));
        $this->assertEquals(false, $obj->get('Fake'));
        $this->assertEquals(false, $obj->get_first('Fake'));
        $this->assertEquals(true, $obj->isValid());
        $this->assertEquals(array('Bar','Baz','Baz'), $obj->get('Foo'));
        $obj->array_dump();
    }

    public function testCaAdmin()
    {
        DatacapCaAdmin::caLanguage();
        CoreLocal::set('PaycardsDatacapMode', 2);
        DatacapCaAdmin::caLanguage();
        CoreLocal::set('PaycardsDatacapMode', 3);
        DatacapCaAdmin::caLanguage();
        $funcs = array(
            'keyChange',
            'paramDownload',
            'keyReport',
            'statsReport',
            'declineReport',
            'paramReport',
        );
        foreach ($funcs as $func) {
            $this->assertInternalType('string', DatacapCaAdmin::$func());
        }
        $xml = file_get_contents(__DIR__ . '/responses/dc.auth.approved.xml');
        $this->assertInternalType('array', DatacapCaAdmin::parseResponse($xml));
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
        // source: Visa Test Card using Sign&Pay w/ test keys
        $pan = '02E600801F2E2700039B25423430303330302A2A2A2A2A2A363738315E544553542F4D50535E313531322A2A2A2A2A2A2A2A2A2A2A2A2A3F3B3430303330302A2A2A2A2A2A363738313D313531322A2A2A2A2A2A2A2A2A2A2A2A2A2A2A2A3FA7284186B3E8E1A3E2AD8548E732DBB5B33285117FB1B0CDBA6D732E5DF031DE3CB590DE2E02BDEF6182373B7401A3E3D304013C85D3BEFDEBF552A3C30914246B0145538F2E5856885CAA06FF64E201CB974CD506ADDCB22C9F3BF500C62310C9C88B56FD2BDF6E59481BC4B6C4F034264B2C38F8FF6F4405D563AA7D49B82221111010000000E001BFXXXX03';
        $info = EncBlock::parseEncBlock($pan);
        $this->assertEquals('D304013C85D3BEFDEBF552A3C30914246B0145538F2E5856885CAA06FF64E201CB974CD506ADDCB2', $info['Block']);
        $this->assertEquals('MagneSafe', $info['Format']);
        $this->assertEquals('21111010000000E001BF', $info['Key']);
        $this->assertEquals('Visa', $info['Issuer']);
        $this->assertEquals('TEST/MPS', $info['Name']);
        $this->assertEquals('6781', $info['Last4']);

        $magtek = '%B4003000050006781^TEST/MPS^13050000000000000?;4003000050006781=13050000000000000000?|0600|96F7CCEB8461264BB3CB3F4539163C8C59E87F2B16F1E876C778A3A15CF840422FAFF02FA2E27FD4DBC29B38535069B9|BDEC23AAA899006C36843F14E0F6A6472C8CDF81271764E160B455FC55AA5DD05F2AD04769614A91||61402200|B54A267EAAEB5B9A85212421B09BEA3B6F4AC894DBDE5A246E2780F461E63C6175C92D0F62703CAC551A206D66760744172CF7E14A223605|B01F8C4072210AA|BF6325ABD6A63EE7|9012090B01F8C4000007|F7D7||0000';
        $info = EncBlock::parseEncBlock($magtek);
        $this->assertEquals('BDEC23AAA899006C36843F14E0F6A6472C8CDF81271764E160B455FC55AA5DD05F2AD04769614A91', $info['Block']);
        $this->assertEquals('MagneSafe', $info['Format']);
        $this->assertEquals('9012090B01F8C4000007', $info['Key']);
        $this->assertEquals('Unknown', $info['Issuer']);
        $this->assertEquals('TEST/MPS', $info['Name']);
        $this->assertEquals('6781', $info['Last4']);

        $ingenico = '23.0%B4003000000006781^TEST/MPS^15120000000000000?@@;4003000000006781=15120000000000000000?@@956959220A1B34705735A3035B017D4B3C5DD67575DC0BFEB85A02A71E3F8C6A67160D720F37CBCE16E061D14D520EAC:21111010000002600182:320D3C963EF3A21D730A9B467C8AE43022DDC9241BB3D2FEBD936773191B55BE6F2948589ABBA829:21111010000002600183';
        $info = EncBlock::parseEncBlock($ingenico);
        $this->assertEquals('320D3C963EF3A21D730A9B467C8AE43022DDC9241BB3D2FEBD936773191B55BE6F2948589ABBA829', $info['Block']);
        $this->assertEquals('MagneSafe', $info['Format']);
        $this->assertEquals('21111010000002600183', $info['Key']);
        $this->assertEquals('Unknown', $info['Issuer']);
        $this->assertEquals('TEST/MPS', $info['Name']);
        $this->assertEquals('6781', $info['Last4']);

        $pin = str_repeat('F', 36);
        EncBlock::parsePinBlock($pin);
        $pin .= '0';
        EncBlock::parsePinBlock($pin);
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
        try {
            PaycardDialogs::enabledCheck();
        } catch (Exception $ex) {}
        CoreLocal::set('CCintegrate', 1);
        $this->assertEquals(true, PaycardDialogs::enabledCheck());
        CoreLocal::set('CCintegrate', '');

        CoreLocal::set('paycard_exp', date('my'));
        $this->assertEquals(true, PaycardDialogs::validateCard('4111111111111111'));
        CoreLocal::set('paycard_exp', date('0101'));
        try {
            PaycardDialogs::validateCard('4111111111111111');
        } catch (Exception $ex) {}
        try {
            PaycardDialogs::validateCard('4111111111111112'); // bad luhn checksum
        } catch (Exception $ex) {}

        try {
            PaycardDialogs::voidableCheck('1111', array(1,1,1));
        } catch (Exception $ex) {}
        SQLManager::addResult(array('transID'=>1));
        SQLManager::addResult(array('transID'=>1));
        try {
            PaycardDialogs::voidableCheck('1111', array(1,1,1)); // too many results
        } catch (Exception $ex) {}
        SQLManager::clear();
        SQLManager::addResult(array('transID'=>1));
        $this->assertEquals(1, PaycardDialogs::voidableCheck('1111', array(1,1,1)));

        $this->assertInternalType('string', PaycardDialogs::invalidMode());

        try {
            PaycardDialogs::getRequest('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::addResult(array(0=>1));
        SQLManager::addResult(array(0=>1));
        try {
            PaycardDialogs::getRequest('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::clear();
        SQLManager::addResult(array(0=>1));
        $this->assertEquals(array(0=>1), PaycardDialogs::getRequest('1-1-1', 1));

        try {
            PaycardDialogs::getResponse('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::addResult(array(0=>1));
        SQLManager::addResult(array(0=>1));
        try {
            PaycardDialogs::getResponse('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::clear();
        SQLManager::addResult(array(0=>1));
        $this->assertEquals(array(0=>1), PaycardDialogs::getResponse('1-1-1', 1));

        try {
            PaycardDialogs::getTenderLine('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::addResult(array(0=>1));
        SQLManager::addResult(array(0=>1));
        try {
            PaycardDialogs::getTenderLine('1-1-1', 1);
        } catch (Exception $ex) {}
        SQLManager::clear();
        SQLManager::addResult(array(0=>1));
        $this->assertEquals(array(0=>1), PaycardDialogs::getTenderLine('1-1-1', 1));

        $this->assertEquals(true, PaycardDialogs::notVoided('1-1-1', 1));
        try {
            SQLManager::addResult(array(0=>1, 'transID'=>1));
            PaycardDialogs::notVoided('1-1-1', 1);
        } catch (Exception $ex) {}

        $response = array(
            'commErr' => 0,
            'httpCode' => 200,
            'validResponse' => 1,
            'xResponseCode' => 1,
            'xTransactionID' => 1,
        );
        $request = array('live'=>PaycardLib::paycard_live(PaycardLib::PAYCARD_TYPE_CREDIT));
        $lineitem = array(
            'trans_type'=>'T',
            'trans_subtype'=>'CC',
            'voided'=>0,
            'trans_status'=>'',
        );
        $this->assertEquals(true, PaycardDialogs::validateVoid($request, $response, $lineitem, 1));
    }

    public function testReqResp()
    {
        $req = new PaycardRequest('1-1-1');
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

        $resp = new PaycardResponse($req, array('curlTime'=>0, 'curlErr'=>0, 'curlHTTP'=>200));
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
        $req = new PaycardVoidRequest('1-1-1');
        try {
            $req->findOriginal();
        } catch (Exception $ex){}
        SQLManager::addResult(array('refNum'=>1,'xTransactionID'=>1,'amount'=>1,'xToken'=>1,'processData'=>1,'acqRefData'=>1,'xApprovalNumber'=>1,'mode'=>1,'cardType'=>1));
        $req->findOriginal();
        SQLManager::clear();
        $req->saveRequest();

        $req = new PaycardGiftRequest('1-1-1');
    }
}

