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

    static private $bin_ranges = array(
        array('min' => 3000000, 'max' => 3099999, 'issuer'=> "Diners Club", 'accepted'=>false),
        array('min'=>3400000, 'max'=>3499999, 'issuer'=>"American Express",'accepted'=>true),
        array('min'=>3528000, 'max'=>3589999, 'issuer'=>"JCB",       'accepted'=>true), // Japan Credit Bureau, accepted via Discover
        array('min'=>3600000, 'max'=>3699999, 'issuer'=>"MasterCard",'accepted'=>true), // Diners Club issued as MC in the US
        array('min'=>3700000, 'max'=>3799999, 'issuer'=>"American Express",'accepted'=>true),
        array('min'=>3800000, 'max'=>3899999, 'issuer'=>"Diners Club", 'accepted'=>false), // might be obsolete?
        array('min'=>4000000, 'max'=>4999999, 'issuer'=>"Visa",      'accepted'=>true),
        array('min'=>5100000, 'max'=>5599999, 'issuer'=>"MasterCard",'accepted'=>true),
        array('min'=>6011000, 'max'=>6011999, 'issuer'=>"Discover",  'accepted'=>true),
        array('min'=>6221260, 'max'=>6229259, 'issuer'=>"UnionPay",  'accepted'=>true), // China UnionPay, accepted via Discover
        array('min'=>6500000, 'max'=>6599999, 'issuer'=>"Discover",  'accepted'=>true),
        array('min'=>6500000, 'max'=>6599999, 'issuer'=>"Discover",  'accepted'=>true),
        array('min'=>5076800, 'max'=>5076809, 'issuer'=>"EBT (AL)",  'accepted'=>'ebt'),
        array('min'=>5076840, 'max'=>5076849, 'issuer'=>"EBT (AL*)",  'accepted'=>'ebt'),
        array('min'=>5076950, 'max'=>5076959, 'issuer'=>"EBT (AK)",  'accepted'=>'ebt'),
        array('min'=>5077060, 'max'=>5077069, 'issuer'=>"EBT (AZ)",  'accepted'=>'ebt'),
        array('min'=>6100930, 'max'=>6100939, 'issuer'=>"EBT (AR)",  'accepted'=>'ebt'),
        array('min'=>5076850, 'max'=>5076859, 'issuer'=>"EBT (AR*)",  'accepted'=>'ebt'),
        array('min'=>5077190, 'max'=>5077199, 'issuer'=>"EBT (CA)",  'accepted'=>'ebt'),
        array('min'=>5076810, 'max'=>5076819, 'issuer'=>"EBT (CO)",  'accepted'=>'ebt'),
        array('min'=>5077130, 'max'=>5077139, 'issuer'=>"EBT (DE)",  'accepted'=>'ebt'),
        array('min'=>5077070, 'max'=>5077079, 'issuer'=>"EBT (DC)",  'accepted'=>'ebt'),
        array('min'=>5081390, 'max'=>5081399, 'issuer'=>"EBT (FL)",  'accepted'=>'ebt'),
        array('min'=>5076860, 'max'=>5076869, 'issuer'=>"EBT (FL*)",  'accepted'=>'ebt'),
        array('min'=>5081480, 'max'=>5081489, 'issuer'=>"EBT (GA)",  'accepted'=>'ebt'),
        array('min'=>5076870, 'max'=>5076879, 'issuer'=>"EBT (GA*)",  'accepted'=>'ebt'),
        array('min'=>5780360, 'max'=>5780369, 'issuer'=>"EBT (GUAM)",  'accepted'=>'ebt'),
        array('min'=>5076980, 'max'=>5076989, 'issuer'=>"EBT (HI)",  'accepted'=>'ebt'),
        array('min'=>5076920, 'max'=>5076929, 'issuer'=>"EBT (ID)",  'accepted'=>'ebt'),
        array('min'=>5077040, 'max'=>5077049, 'issuer'=>"EBT (IN)",  'accepted'=>'ebt'),
        array('min'=>6014130, 'max'=>6014139, 'issuer'=>"EBT (KS)",  'accepted'=>'ebt'),
        array('min'=>5077090, 'max'=>5077099, 'issuer'=>"EBT (KY)",  'accepted'=>'ebt'),
        array('min'=>5076880, 'max'=>5076889, 'issuer'=>"EBT (KY*)",  'accepted'=>'ebt'),
        array('min'=>5044760, 'max'=>5044769, 'issuer'=>"EBT (LA)",  'accepted'=>'ebt'),
        array('min'=>6005280, 'max'=>6005289, 'issuer'=>"EBT (MD)",  'accepted'=>'ebt'),
        array('min'=>5077110, 'max'=>5077119, 'issuer'=>"EBT (MI)",  'accepted'=>'ebt'),
        array('min'=>6104230, 'max'=>6104239, 'issuer'=>"EBT (MN)",  'accepted'=>'ebt'),
        array('min'=>5077180, 'max'=>5077189, 'issuer'=>"EBT (MS)",  'accepted'=>'ebt'),
        array('min'=>5076830, 'max'=>5076839, 'issuer'=>"EBT (MO)",  'accepted'=>'ebt'),
        array('min'=>5076890, 'max'=>5076899, 'issuer'=>"EBT (MO*)",  'accepted'=>'ebt'),
        array('min'=>5077140, 'max'=>5077149, 'issuer'=>"EBT (MT)",  'accepted'=>'ebt'),
        array('min'=>5077160, 'max'=>5077169, 'issuer'=>"EBT (NE)",  'accepted'=>'ebt'),
        array('min'=>5077150, 'max'=>5077159, 'issuer'=>"EBT (NV)",  'accepted'=>'ebt'),
        array('min'=>5077010, 'max'=>5077019, 'issuer'=>"EBT (NH)",  'accepted'=>'ebt'),
        array('min'=>6104340, 'max'=>6104349, 'issuer'=>"EBT (NJ)",  'accepted'=>'ebt'),
        array('min'=>5866160, 'max'=>5866169, 'issuer'=>"EBT (NM)",  'accepted'=>'ebt'),
        array('min'=>5081610, 'max'=>5081619, 'issuer'=>"EBT (NC)",  'accepted'=>'ebt'),
        array('min'=>5076900, 'max'=>5076909, 'issuer'=>"EBT (NC*)",  'accepted'=>'ebt'),
        array('min'=>5081320, 'max'=>5081329, 'issuer'=>"EBT (ND)",  'accepted'=>'ebt'),
        array('min'=>5077000, 'max'=>5077009, 'issuer'=>"EBT (OH)",  'accepted'=>'ebt'),
        array('min'=>5081470, 'max'=>5081479, 'issuer'=>"EBT (OK)",  'accepted'=>'ebt'),
        array('min'=>5076930, 'max'=>5076939, 'issuer'=>"EBT (OR)",  'accepted'=>'ebt'),
        array('min'=>5076820, 'max'=>5076829, 'issuer'=>"EBT (RI)",  'accepted'=>'ebt'),
        array('min'=>5081320, 'max'=>5081329, 'issuer'=>"EBT (SD)",  'accepted'=>'ebt'),
        array('min'=>5077020, 'max'=>5077029, 'issuer'=>"EBT (TN)",  'accepted'=>'ebt'),
        array('min'=>5076910, 'max'=>5076919, 'issuer'=>"EBT (TN*)",  'accepted'=>'ebt'),
        array('min'=>5077210, 'max'=>5077219, 'issuer'=>"EBT (USVI)",  'accepted'=>'ebt'),
        array('min'=>6010360, 'max'=>6010369, 'issuer'=>"EBT (UT)",  'accepted'=>'ebt'),
        array('min'=>5077050, 'max'=>5077059, 'issuer'=>"EBT (VT)",  'accepted'=>'ebt'),
        array('min'=>6220440, 'max'=>6220449, 'issuer'=>"EBT (VA)",  'accepted'=>'ebt'),
        array('min'=>5077100, 'max'=>5077109, 'issuer'=>"EBT (WA)",  'accepted'=>'ebt'),
        array('min'=>5077200, 'max'=>5077209, 'issuer'=>"EBT (WV)",  'accepted'=>'ebt'),
        array('min'=>5077080, 'max'=>5077089, 'issuer'=>"EBT (WI)",  'accepted'=>'ebt'),
        array('min'=>5053490, 'max'=>5053499, 'issuer'=>"EBT (WY)",  'accepted'=>'ebt'),
    );
    
    static private $bin19s = array(
        array('min'=>7019208, 'max'=>7019208,  'issuer'=>"Co-op Gift", 'accepted'=>true), // NCGA gift cards
        array('min'=>7018525, 'max'=>7018525,  'issuer'=>"Valutec Gift", 'accepted'=>false), // valutec test cards (linked to test merchant/terminal ID)
        array('min'=>6050110, 'max'=>6050110,  'issuer'=>"Co-Plus Gift Card", 'accepted'=>true),
        array('min'=>6014530, 'max'=>6014539,  'issuer'=>"EBT (IL)",   'accepted'=>'ebt'),
        array('min'=>6274850, 'max'=>6274859,  'issuer'=>"EBT (IA)",   'accepted'=>'ebt'),
        array('min'=>5077030, 'max'=>5077039,  'issuer'=>"EBT (ME)",   'accepted'=>'ebt'),
        array('min'=>6004860, 'max'=>6004869,  'issuer'=>"EBT (NY)",   'accepted'=>'ebt'),
        array('min'=>6007600, 'max'=>6007609,  'issuer'=>"EBT (PA)",   'accepted'=>'ebt'),
        array('min'=>6104700, 'max'=>6104709,  'issuer'=>"EBT (SC)",   'accepted'=>'ebt'),
        array('min'=>6100980, 'max'=>6100989,  'issuer'=>"EBT (TX)",   'accepted'=>'ebt'),
    );

static private function identifyBin($bin_range, $iin, $ebt_accept)
{
    $accepted = true;
    $issuer = 'Unknown';
    foreach ($bin_range as $range) {
        if ($iin >= $range['min'] && $iin <= $range['max']) {
            $issuer = $range['issuer'];
            $accepted = $range['accepted'];
            if ($accepted === 'ebt') {
                $accepted = $ebt_accept;
            }
            break;
        }
    }

    return array($accepted, $issuer);
}

// identify payment card type, issuer and acceptance based on card number
// individual functions are based on this one
/**
  Identify card based on number
  @param $pan card number
  @return array with keys:
   - 'type' paycard type constant
   - 'issuer' Vista, MasterCard, etc
   - 'accepted' boolean, whether card is accepted
   - 'test' boolean, whether number is a testing card

   EBT-Specific Notes:
   EBT BINs added 20Mar14 by Andy
   Based on NACHA document; that document claims to be current
   as of 30Sep10.

   Issuer is normally give as EBT (XX) where XX is the
   two character state postal abbreviation. GUAM is Guam
   and USVI is US Virgin Islands. A few states list both
   a state BIN number and a federal BIN number. In these
   cases there's an asterisk after the postal abbreviation.
   Maine listed both a state and federal BIN but they're 
   identical so I don't know how to distinguish. The PAN
   length is not listed for Wyoming. I guessed 16 since 
   that's most common.
*/
static public function paycard_info($pan) 
{
    $len = strlen($pan);
    $iin = (int)substr($pan,0,7);
    $issuer = "Unknown";
    $type = self::PAYCARD_TYPE_UNKNOWN;
    $accepted = false;
    $ebt_accept = true;
    $test = false;
    if ($len >= 13 && $len <= 16) {
        $type = self::PAYCARD_TYPE_CREDIT;
        list($accepted, $issuer) = self::identifyBin(self::$bin_ranges, $iin, $ebt_accept);
    } elseif ($len == 18) {
        if(      $iin>=6008900 && $iin<=6008909) { $issuer="EBT (CT)";   $accepted=$ebt_accept; }
        else if( $iin>=6008750 && $iin<=6008759) { $issuer="EBT (MA)";   $accepted=$ebt_accept; }
    } elseif ($len == 19) {
        $type = self::PAYCARD_TYPE_GIFT;
        list($accepted, $issuer) = self::identifyBin(self::$bin19s, $iin, $ebt_accept);
    } elseif (substr($pan,0,8) == "02E60080" || substr($pan, 0, 5) == "23.0%" || substr($pan, 0, 5) == "23.0;") {
        $type = self::PAYCARD_TYPE_ENCRYPTED;
        $accepted = true;
    } elseif (substr($pan,0,2) === '02' && substr($pan,-2) === '03' && strstr($pan, '***')) {
        $type = self::PAYCARD_TYPE_ENCRYPTED;
        $accepted = true;
    }
    return array('type'=>$type, 'issuer'=>$issuer, 'accepted'=>$accepted, 'test'=>$test);
} // paycard_info()

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
    if( CoreLocal::get("training") != 0 || CoreLocal::get("CashierNo") == 9999)
        return 0;
    // special session vars for each card type
    if( $type === self::PAYCARD_TYPE_CREDIT) {
        if( CoreLocal::get("CCintegrate") != 1)
            return 0;
    }
    return 1;
} // paycard_live()


/**
  Clear paycard variables from session
*/
static public function paycard_reset() 
{

    // make sure this matches session.php!!!
    CoreLocal::set("paycard_manual",0);
    CoreLocal::set("paycard_amount",0.00);
    CoreLocal::set("paycard_mode",0);
    CoreLocal::set("paycard_id",0);
    CoreLocal::set("paycard_PAN",'');
    CoreLocal::set("paycard_exp",'');
    CoreLocal::set("paycard_name",'Customer');
    CoreLocal::set("paycard_tr1",false);
    CoreLocal::set("paycard_tr2",false);
    CoreLocal::set("paycard_tr3",false);
    CoreLocal::set("paycard_type",0);
    CoreLocal::set("paycard_issuer",'Unknown');
    CoreLocal::set("paycard_response",array());
    CoreLocal::set("paycard_trans",'');
    CoreLocal::set("paycard_cvv2",'');
    CoreLocal::set('PaycardRetryBalanceLimit', 0);
} // paycard_reset()

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
static public function paycard_errorText($title, $code, $text, $retry, $standalone, $refuse, $carbon, $tellIT, $type) 
{
    // pick the icon
    if( $carbon)
        $msg = "<img src='graphics/blacksquare.gif'> ";
    else if( $refuse)
        $msg = "<img src='graphics/bluetri.gif'> ";
    else
        $msg = "<img src='graphics/redsquare.gif'> ";
    // write the text
    $msg .= "<b>".trim($title)."</b>";
    //if( $code)
        $msg .= "<br><font size=-2>(#R.".$code.")</font>";
    $msg .= "<font size=-1><br><br>";
    if( $text)
        $msg .= $text."<br>";
    // write the options
    $opt = "";
    if( $refuse)     { $opt .= ($opt ? ", or" : "") . " request <b>other payment</b>"; }
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
} // paycard_errorText()


// display a paycard-related error due to cashier mistake
static public function paycard_msgBox($type, $title, $msg, $action) 
{
    $header = "IT CORE - Payment Card";
    $boxmsg = "<span class=\"larger\">".trim($title)."</span><p />";
    $boxmsg .= trim($msg)."<p />".trim($action);
    return DisplayLib::boxMsg($boxmsg,$header,True);
} // paycard_msgBox()


// display a paycard-related error due to system, network or other non-cashier mistake
static public function paycard_errBox($type, $title, $msg, $action) 
{
    return DisplayLib::xboxMsg("<b>".trim($title)."</b><p><font size=-1>".trim($msg)."<p>".trim($action)."</font>");
} // paycard_errBox()

static private $paycardDB = null;

static public function paycard_db()
{
    if (self::$paycardDB === null) {
        self::$paycardDB = Database::tDataConnect();
    }

    return self::$paycardDB;
}

static public function paycard_db_query($query_text,$link){
    return self::$paycardDB->query($query_text);
}

static public function paycard_db_num_rows($result){
    return self::$paycardDB->numRows($result);
}

static public function paycard_db_fetch_row($result){
    return self::$paycardDB->fetchRow($result);
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

