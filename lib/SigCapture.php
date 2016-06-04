<?php

class SigCapture
{
    public function __construct($conf)
    {
        $this->conf = $conf;
    }

    public function save($file, $dbc)
    {
        $bmp = file_get_contents($file);
        $format = 'BMP';
        $imgContent = $bmp;

        $capQ = 'INSERT INTO CapturedSignature
                    (tdate, emp_no, register_no, trans_no,
                     trans_id, filetype, filecontents)
                 VALUES
                    (?, ?, ?, ?,
                     ?, ?, ?)';
        $capP = $dbc->prepare($capQ);
        $args = array(
            date('Y-m-d H:i:s'),
            $this->conf->get('CashierNo'),
            $this->conf->get('laneno'),
            $this->conf->get('transno'),
            $this->conf->get('paycard_id'),
            $format,
            $imgContent,
        );
        $dbc->execute($capP, $args);

        unlink($file);
    } 

    public function required()
    {
        // Signature Capture support
        // If:
        //   a) enabled
        //   b) a Credit transaction
        //   c) Over limit threshold OR a return
        $isCredit = ($this->conf->get('CacheCardType') == 'CREDIT' || $this->conf->get('CacheCardType') == '') ? true : false;
        // gift doesn't set CacheCardType so customer swipes and
        // cashier types don't overwrite each other's type
        if ($this->conf->get('paycard_type') == PaycardLib::PAYCARD_TYPE_GIFT) {
            $isCredit = false;
        }
        $needSig = ($this->conf->get('paycard_amount') > $this->conf->get('CCSigLimit') || $this->conf->get('paycard_amount') < 0) ? true : false;
        $isVoid = ($this->conf->get('paycard_mode') == PaycardLib::PAYCARD_MODE_VOID) ? true : false;

        return ($this->conf->get("PaycardsSigCapture") == 1 && $isCredit && $needSig && !$isVoid); 
    }
}

