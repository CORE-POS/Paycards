<?php
/*******************************************************************************

    Copyright 2001, 2004 Wedge Community Co-op

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

/**
 @class PaycardLib
 @brief Defines constants and functions for card processing.
*/

class PaycardLib {

    const PAYCARD_MODE_BALANCE       =1;
    const PAYCARD_MODE_AUTH          =2;
    const PAYCARD_MODE_VOID          =3; // for voiding tenders/credits, rung in as T
    const PAYCARD_MODE_ACTIVATE      =4;
    const PAYCARD_MODE_ADDVALUE      =5;
    const PAYCARD_MODE_CASHOUT       =7; // for cashing out a wedgecard

    const PAYCARD_TYPE_UNKNOWN       =0;
    const PAYCARD_TYPE_CREDIT        =1;
    const PAYCARD_TYPE_GIFT          =2;
    const PAYCARD_TYPE_STORE         =3;
    const PAYCARD_TYPE_ENCRYPTED       =4;

    const PAYCARD_ERR_OK             =1;
    const PAYCARD_ERR_NOSEND        =-1;
    const PAYCARD_ERR_COMM          =-2;
    const PAYCARD_ERR_TIMEOUT       =-3;
    const PAYCARD_ERR_DATA          =-4;
    const PAYCARD_ERR_PROC          =-5;
    const PAYCARD_ERR_CONTINUE        =-6;
    const PAYCARD_ERR_NSF_RETRY        =-7;
    const PAYCARD_ERR_TRY_VERIFY    =-8;

// helper static public function to format money amounts pre-php5
static public function paycard_moneyFormat($amt) {
    $sign = "";
    if( $amt < 0) {
        $sign = "-";
        $amt = -$amt;
    }
    return $sign."$".number_format($amt,2);
} // paycard_moneyFormat

// helper static public function to build error messages
static public function paycardErrorText($title, $code, $retry, $standalone, $carbon, $tellIT, $type) 
{
    // pick the icon
    $msg = "<img src='graphics/" . ($carbon ? 'blacksquare' : 'redsquare') . ".gif'> ";
    // write the text
    $msg .= "<b>".trim($title)."</b>";
    $msg .= "<br><font size=-2>(#R.".$code.")</font>";
    $msg .= "<font size=-1><br><br>";
    // write the options
    $opt = "";
    if( $retry)      { $opt .= ($opt ? ", or" : "") . " <b>retry</b>";                 }
    if( $standalone) { $opt .= ($opt ? ", or" : "") . " process in <b>standalone</b>"; }
    if( $carbon) {
        if( $type == self::PAYCARD_TYPE_CREDIT) { $opt .= ($opt ? ", or" : "") . " take a <b>carbon</b>"; }
        else { $opt .= ($opt ? ", or" : "") . " process <b>manually</b>"; }
    }
    if( $opt)        { $opt = "Please " . $opt . "."; }
    if( $tellIT)     { $opt = trim($opt." <i>(Notify IT)</i>"); }
    if( $opt)
        $msg .= $opt."<br>";
    $msg .= "<br>";
    // retry option?
    if( $retry) {
        $msg .= "[enter] to retry<br>";
    } else {
        CoreLocal::set("strEntered","");
        CoreLocal::set("strRemembered","");
    }
    $msg .= "[clear] to cancel</font>";
    return $msg;
}


// display a paycard-related error due to cashier mistake
static public function paycardMsgBox($title, $msg, $action) 
{
    $header = "IT CORE - Payment Card";
    $boxmsg = "<span class=\"larger\">".trim($title)."</span><p />";
    $boxmsg .= trim($msg)."<p />".trim($action);
    return DisplayLib::boxMsg($boxmsg,$header,True);
}


// display a paycard-related error due to system, network or other non-cashier mistake
static public function paycardErrBox($title, $msg, $action) 
{
    return DisplayLib::xboxMsg("<b>".trim($title)."</b><p><font size=-1>".trim($msg)."<p>".trim($action)."</font>");
} 

static public function paycard_db()
{
    return Database::tDataConnect();
}

static public function setupAuthJson($json)
{
    if (CoreLocal::get("paycard_amount") == 0) {
        CoreLocal::set("paycard_amount",CoreLocal::get("amtdue"));
    }
    CoreLocal::set("paycard_id",CoreLocal::get("LastID")+1); // kind of a hack to anticipate it this way..
    $pluginInfo = new Paycards();
    $json['main_frame'] = $pluginInfo->pluginUrl().'/gui/paycardboxMsgAuth.php';
    $json['output'] = '';

    return $json;
}

static public function validateAmount()
{
    $amt = CoreLocal::get('paycard_amount');
    $due = CoreLocal::get("amtdue");
    $type = CoreLocal::get("CacheCardType");
    $cashback = CoreLocal::get('CacheCardCashBack');
    $balanceLimit = CoreLocal::get('PaycardRetryBalanceLimit');
    if ($type == 'EBTFOOD') {
        $due = CoreLocal::get('fsEligible');
    }
    if ($cashback > 0) $amt -= $cashback;
    if (!is_numeric($amt) || abs($amt) < 0.005) {
        return array(false, 'Enter a different amount');
    } elseif ($amt > 0 && $due < 0) {
        return array(false, 'Enter a negative amount');
    } elseif ($amt < 0 && $due > 0) {
        return array(false, 'Enter a positive amount');
    } elseif (($amt-$due)>0.005 && $type != 'DEBIT' && $type != 'EBTCASH') {
        return array(false, 'Cannot exceed amount due');
    } elseif (($amt-$due-0.005)>$cashback && ($type == 'DEBIT' || $type == 'EBTCASH')) {
        return array(false, 'Cannot exceed amount due plus cashback');
    } elseif ($balanceLimit > 0 && ($amt-$balanceLimit) > 0.005) {
        return array(false, 'Cannot exceed card balance');
    } else {
        return array(true, 'valid');
    }

    return array(false, 'invalid');
}

/*
summary of ISO standards for credit card magnetic stripe data tracks:
http://www.cyberd.co.uk/support/technotes/isocards.htm
(hex codes and character representations do not match ASCII - they are defined in the ISO spec)

TRACK 1
    {S} start sentinel: 0x05 '%'
    {C} format code: for credit cards, 0x22 'B'
    {F} field seperator: 0x3F '^'
    {E} end sentinel: 0x1F '?'
    {V} checksum character
    format: {S}{C}cardnumber{F}cardholdername{F}extra{E}{V}
        'extra' begins with expiration date as YYMM, then service code CCC, then unregulated extra data
    length: 79 characters total

TRACK 2
    {S} start sentinel: 0x0B ';'
    {F} field seperator: 0x0D '='
    {E} end sentinel: 0x0F '?'
    {V} checksum character
    format: {S}cardnumber{F}extra{E}{V}
        'extra' begins with expiration date as YYMM, then service code CCC, then unregulated extra data
    length: 40 characters total

TRACK 3
    {S} start sentinel: 0x0B ';'
    {C} format code: varies
    {F} field seperator: 0x0D '='
    {E} end sentinel: 0x0F '?'
    {V} checksum character
    format: {S}{C}{C}data{F}data{E}{V}
    length: 107 characters
*/

}

