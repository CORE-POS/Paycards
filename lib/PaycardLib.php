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

/**
  Check whether paycards of a given type are enabled
  @param $type is a paycard type constant
  @return
   - 1 if type is enabled
   - 0 if type is disabled
*/
static public function paycard_live($type = self::PAYCARD_TYPE_UNKNOWN) 
{
    // these session vars require training mode no matter what card type
    if (CoreLocal::get("training") != 0 || CoreLocal::get("CashierNo") == 9999)
        return 0;

    // special session vars for each card type
    if ($type === self::PAYCARD_TYPE_CREDIT && CoreLocal::get('CCintegrate') != 1) {
        return 0;
    }

    return 1;
} // paycard_live()

/**
  Clear card data variables from session

  <b>Storing card data in session is
  not recommended</b>.
*/
static public function paycard_wipe_pan()
{
    CoreLocal::set("paycard_tr1",false);
    CoreLocal::set("paycard_tr2",false);
    CoreLocal::set("paycard_tr3",false);
    CoreLocal::set("paycard_PAN",'');
    CoreLocal::set("paycard_exp",'');
}

// return a card number with digits replaced by *s, except for some number of leading or tailing digits as requested
static public function paycard_maskPAN($pan,$first,$last) {
    $mask = "";
    // sanity check
    $len = strlen($pan);
    if( $first + $last >= $len)
        return $pan;
    // prepend requested digits
    if( $first > 0)
        $mask .= substr($pan, 0, $first);
    // mask middle
    $mask .= str_repeat("*", $len - ($first+$last));
    // append requested digits
    if( $last > 0)
        $mask .= substr($pan, -$last);
    
    return $mask;
} // paycard_maskPAN()


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

static private function getIssuerOverride($issuer)
{
    if (CoreLocal::get('PaycardsTenderCodeVisa') && $issuer == 'Visa') {
        return array(CoreLocal::get('PaycardsTenderCodeVisa'));
    } elseif (CoreLocal::get('PaycardsTenderCodeMC') && $issuer == 'MasterCard') {
        return array(CoreLocal::get('PaycardsTenderCodeMC'));
    } elseif (CoreLocal::get('PaycardsTenderCodeDiscover') && $issuer == 'Discover') {
        return array(CoreLocal::get('PaycardsTenderCodeDiscover'));
    } elseif (CoreLocal::get('PaycardsTenderCodeAmex') && $issuer == 'American Express') {
        return array(CoreLocal::get('PaycardsTenderCodeAmex'));
    } else {
        return false;
    }
}

static private function getTenderConfig($type)
{ 
    switch ($type) {
        case 'DEBIT':
            return array(
                array(CoreLocal::get('PaycardsTenderCodeDebit')),
                'DC',
                'Debit Card',
            );
        case 'EBTCASH':
            return array(
                array(CoreLocal::get('PaycardsTenderCodeEbtCash')),
                'EC',
                'EBT Cash',
            );
        case 'EBTFOOD':
            return array(
                array(CoreLocal::get('PaycardsTenderCodeEbtFood')),
                'EF',
                'EBT Food',
            );
        case 'EMV':
            return array(
                array(CoreLocal::get('PaycardsTenderCodeEmv')),
                'CC',
                'Credit Card',
            );
        case 'GIFT':
        case 'PREPAID':
            return array(
                array(CoreLocal::get('PaycardsTenderCodeGift')),
                'GD',
                'Gift Card',
            );
        case 'CREDIT':
        default:
            return array(
                array(CoreLocal::get('PaycardsTenderCodeCredit')),
                'CC',
                'Credit Card',
            );
    }
}

/**
  Lookup user-configured tender
  Failover to defaults if tender does not exist
  Since we already have an authorization at this point,
  adding a default tender record to the transaction
  is better than issuing an error message
*/
static public function getTenderInfo($type, $issuer)
{
    $dbc = Database::pDataConnect();
    $lookup = $dbc->prepare('
        SELECT TenderName,
            TenderCode
        FROM tenders
        WHERE TenderCode = ?');
    
    list($args, $default_code, $default_description) = self::getTenderConfig($type);
    $override = self::getIssuerOverride($issuer);
    if ($override !== false) {
        $args = $override;
    }
    
    $found = $dbc->execute($lookup, $args);
    if ($found === false || $dbc->numRows($found) == 0) {
        return array($default_code, $default_description);
    } else {
        $row = $dbc->fetchRow($found);
        return array($row['TenderCode'], $row['TenderName']);
    }
}

static public function setupAuthJson($json)
{
    if (CoreLocal::get("paycard_amount") == 0) {
        CoreLocal::set("paycard_amount",CoreLocal::get("amtdue"));
    }
    CoreLocal::set("paycard_id",CoreLocal::get("LastID")+1); // kind of a hack to anticipate it this way..
    $plugin_info = new Paycards();
    $json['main_frame'] = $plugin_info->pluginUrl().'/gui/paycardboxMsgAuth.php';
    $json['output'] = '';

    return $json;
}

static public function validateAmount()
{
    $amt = CoreLocal::get('paycard_amount');
    $due = CoreLocal::get("amtdue");
    $type = CoreLocal::get("CacheCardType");
    $cb = CoreLocal::get('CacheCardCashBack');
    $balance_limit = CoreLocal::get('PaycardRetryBalanceLimit');
    if ($type == 'EBTFOOD') {
        $due = CoreLocal::get('fsEligible');
    }
    if ($cb > 0) $amt -= $cb;
    if (!is_numeric($amt) || abs($amt) < 0.005) {
        return array(false, 'Enter a different amount');
    } elseif ($amt > 0 && $due < 0) {
        return array(false, 'Enter a negative amount');
    } elseif ($amt < 0 && $due > 0) {
        return array(false, 'Enter a positive amount');
    } elseif (($amt-$due)>0.005 && $type != 'DEBIT' && $type != 'EBTCASH') {
        return array(false, 'Cannot exceed amount due');
    } elseif (($amt-$due-0.005)>$cb && ($type == 'DEBIT' || $type == 'EBTCASH')) {
        return array(false, 'Cannot exceed amount due plus cashback');
    } elseif ($balance_limit > 0 && ($amt-$balance_limit) > 0.005) {
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

