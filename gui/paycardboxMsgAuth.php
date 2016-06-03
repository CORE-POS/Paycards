<?php
/*******************************************************************************

    Copyright 2001, 2004 Wedge Community Co-op
    Modifications copyright 2010 Whole Foods Co-op

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

use COREPOS\pos\lib\FormLib;
if (!class_exists('AutoLoader')) include_once(dirname(__FILE__).'/../../../lib/AutoLoader.php');

class paycardboxMsgAuth extends PaycardProcessPage {

    function preprocess()
    {
        // check for posts before drawing anything, so we can redirect
        $this->addOnloadCommand("\$('#formlocal').submit(paycardboxmsgAuth.submitWrapper);\n");
        if (FormLib::get('validate') !== '') { // ajax callback to validate inputs
            list($valid, $msg) = PaycardLib::validateAmount();
            echo json_encode(array('valid'=>$valid, 'msg'=>$msg));
            return false;
        } elseif (FormLib::get('reginput', false) !== false) {
            $input = strtoupper(trim(FormLib::get('reginput')));
            // CL always exits
            if ($input === "CL") {
                $this->conf->set("msgrepeat",0);
                $this->conf->set("toggletax",0);
                $this->conf->set("togglefoodstamp",0);
                PaycardLib::paycard_reset();
                $this->conf->set("CachePanEncBlock","");
                $this->conf->set("CachePinEncBlock","");
                $this->conf->set("CacheCardType","");
                $this->conf->set("CacheCardCashBack",0);
                $this->conf->set('ccTermState','swipe');
                UdpComm::udpSend("termReset");
                $this->change_page($this->page_url."gui-modules/pos2.php");
                return False;
            } elseif ($input == "") {
                list($valid, $msg) = PaycardLib::validateAmount();
                if ($valid) {
                    $this->action = "onsubmit=\"return false;\"";    
                    $this->addOnloadCommand("paycard_submitWrapper();");
                }
            } else {
                // any other input is an alternate amount
                $this->conf->set("paycard_amount","invalid");
                if (is_numeric($input)){
                    $this->setAmount($input/100);
                }
            }
            // if we're still here, we haven't accepted a valid amount yet; display prompt again
        } // post?

        return true;
    }

    private function setAmount($amt)
    {
        $this->conf->set("paycard_amount",$amt);
        if ($this->conf->get('CacheCardCashBack') > 0 && $this->conf->get('CacheCardCashBack') <= 40) {
            $this->conf->set('paycard_amount',($amt)+$this->conf->get('CacheCardCashBack'));
        }
    }

    function head_content()
    {
        echo '<script type="text/javascript" src="../js/paycardboxmsgAuth.js"></script>';
    }

    function body_content()
    {
        ?>
        <div class="baseHeight">
        <?php
        // generate message to print
        $type = $this->conf->get("paycard_type");
        $mode = $this->conf->get("paycard_mode");
        $amt = $this->conf->get("paycard_amount");
        $cb = $this->conf->get('CacheCardCashBack');
        $balance_limit = $this->conf->get('PaycardRetryBalanceLimit');
        if ($cb > 0) $amt -= $cb;
        list($valid, $validmsg) = PaycardLib::validateAmount();
        if ($valid === false) {
            echo PaycardLib::paycard_msgBox($type, "Invalid Amount: $amt",
                $validmsg, "[clear] to cancel");
        } elseif ($balance_limit > 0) {
            $msg = "Tender ".PaycardLib::paycard_moneyFormat($amt);
            if ($this->conf->get("CacheCardType") != "") {
                $msg .= " as ".$this->conf->get("CacheCardType");
            } elseif ($this->conf->get('paycard_type') == PaycardLib::PAYCARD_TYPE_GIFT) {
                $msg .= ' as GIFT';
            }
            echo PaycardLib::paycard_msgBox($type,$msg."?","",
                    "Card balance is {$balance_limit}<br>
                    [enter] to continue if correct<br>Enter a different amount if incorrect<br>
                    [clear] to cancel");
        } elseif ($amt > 0) {
            $msg = "Tender ".PaycardLib::paycard_moneyFormat($amt);
            if ($this->conf->get("CacheCardType") != "") {
                $msg .= " as ".$this->conf->get("CacheCardType");
            } elseif ($this->conf->get('paycard_type') == PaycardLib::PAYCARD_TYPE_GIFT) {
                $msg .= ' as GIFT';
            }
            if ($cb > 0) {
                $msg .= ' (CB:'.PaycardLib::paycard_moneyFormat($cb).')';
            }
            $msg .= '?';
            if ($this->conf->get('CacheCardType') == 'EBTFOOD' && abs($this->conf->get('subtotal') - $this->conf->get('fsEligible')) > 0.005) {
                $msg .= '<br />'
                    . _('Not all items eligible');
            }
            echo PaycardLib::paycard_msgBox($type,$msg,"","[enter] to continue if correct<br>Enter a different amount if incorrect<br>[clear] to cancel");
        } elseif( $amt < 0) {
            echo PaycardLib::paycard_msgBox($type,"Refund ".PaycardLib::paycard_moneyFormat($amt)."?","","[enter] to continue if correct<br>Enter a different amount if incorrect<br>[clear] to cancel");
        } else {
            echo PaycardLib::paycard_errBox($type,"Invalid Entry",
                "Enter a different amount","[clear] to cancel");
        }
        $this->conf->set("msgrepeat",2);
        ?>
        </div>
        <?php
    }
}

AutoLoader::dispatch();

