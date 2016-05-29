<?php

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
        $this->assertInternalType('array', $bm->paycard_void(1));
        $this->assertEquals(false, $bm->myRefNum('1-1-1'));
        $this->assertInternalType('array', $bm->lookupTransaction('1-1-1', true, 'lookup'));
        $this->assertInternalType('string', $bm->refnum(1));
        $this->assertInternalType('string', $bm->array2post(array('foo'=>'bar')));
        $soaped = $bm->soapify('action',array('foo'=>'bar'));
        $this->assertInternalType('string', $soaped);
        $this->assertInternalType('string', $bm->desoapify('action', $soaped));
        $this->assertEquals(PaycardLib::PAYCARD_ERR_NOSEND, $bm->setErrorMsg(PaycardLib::PAYCARD_ERR_NOSEND));

        $a = new AuthorizeDotNet();
        $this->assertEquals(true, $a->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(false, $a->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $a->entered(true, array()));
        $this->assertInternalType('array', $a->paycard_void(1));

        $f = new FirstData();
        $this->assertEquals(true, $f->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(false, $f->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $f->entered(true, array()));
        $this->assertInternalType('array', $f->paycard_void(1));

        $g = new GoEMerchant();
        $this->assertEquals(true, $g->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(false, $g->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $g->entered(true, array()));
        $this->assertInternalType('array', $g->paycard_void(1));

        $m = new MercuryGift();
        $this->assertEquals(false, $m->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(true, $m->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $m->entered(true, array()));
        $this->assertInternalType('array', $m->paycard_void(1));

        $v = new Valutec();
        $this->assertEquals(false, $v->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(true, $v->handlesType(PaycardLib::PAYCARD_TYPE_GIFT));
        $this->assertInternalType('array', $v->entered(true, array()));
        $this->assertInternalType('array', $v->paycard_void(1));

        $v = new MercuryE2E();
        $this->assertEquals(false, $v->handlesType(PaycardLib::PAYCARD_TYPE_CREDIT));
        $this->assertEquals(true, $v->handlesType(PaycardLib::PAYCARD_TYPE_ENCRYPTED));
        $this->assertInternalType('array', $v->entered(true, array()));
        $this->assertInternalType('array', $v->paycard_void(1));
    }

    public function testPages()
    {
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
        $this->assertEquals(false, $p->check('foo'));
        $this->assertEquals(true, $p->check('foo?'));
        $this->assertEquals(true, $p->check('02E6008012345'));
        $this->assertEquals(true, $p->check('02***03'));
        $this->assertEquals(true, $p->check('4111111111111111' . date('my')));
        $this->assertInternalType('array', $p->parse('4111111111111111' . date('my')));
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

        CoreLocal::set('paycard_exp', date('my'));
        $this->assertEquals(true, PaycardDialogs::validateCard('4111111111111111'));

        try {
            PaycardDialogs::voidableCheck('1111', array(1,1,1));
        } catch (Exception $ex) {}

        $this->assertInternalType('string', PaycardDialogs::invalidMode());

        try {
            PaycardDialogs::getRequest('1-1-1', 1);
        } catch (Exception $ex) {}

        try {
            PaycardDialogs::getResponse('1-1-1', 1);
        } catch (Exception $ex) {}

        try {
            PaycardDialogs::getTenderLine('1-1-1', 1);
        } catch (Exception $ex) {}

        try {
            PaycardDialogs::notVoided('1-1-1', 1);
        } catch (Exception $ex) {}

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

