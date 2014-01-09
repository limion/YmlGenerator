<?php

/**
 * YmlGenerator class file.
 *
 * @author Vladislav Holovko <vlad.holovko@gmail.com>
 * @link http://www.yiiframework.com/extensions/
 * @copyright Copyright &copy; 2013
 * @license http://www.yiiframework.com/license/
 * 
 * Inspired by http://p2k.ru/archives/52
 */

/**
 * YmlGenerator class is a Yandex.Market YML generator.
 * Supports output to a file or browser.
 * Uses XMLWriter as xml engine.
 * 
 * For generating xml data you need to create your own generator class and implement four methods:
 *  - shopInfo()
 *  - currencies()
 *  - categories()
 *  - offers() 
 * 
 * For example:
 * class MyYmlGenerator extends YmlGenerator {
 *      protected function shopInfo() {...}
 *      protected function currencies() {...}
 *      protected function categories() {...}
 *      protected function offers() {...}
 * }
 * How to implement these methods see the sample class MyYmlGenerator and definition of add* methods below
 */

abstract class YmlGenerator extends CApplicationComponent {
    
    /**
     * Xml file encoding
     * @var string
     */
    public $encoding = 'windows-1251';
    
    /**
     * Output file name. If null 'php://output' is used.
     * @var string
     */
    public $outputFile;
    
    /**
     * Indent string in xml file. False or null means no indent;
     * @var string
     */
    public $indentString = "\t";
    
    /**
     * An array of element names used to describe your web-shop according to YML standart
     * @var array
     */
    public $shopInfoElements = array('name','company','url','platform','version','agency','email');
    
    /**
     * An array of element names used to create an offer according to YML standart
     * @var array
     */
    public $offerElements = array('url', 'price', 'currencyId', 'categoryId', 'market_category', 
            'picture', 'store', 'pickup', 'delivery', 'local_delivery_cost','typePrefix', 
            'vendor', 'vendorCode', 'name' ,'model', 'description', 'sales_notes', 'manufacturer_warranty',
            'seller_warranty','country_of_origin', 'downloadable', 'age','barcode','cpa',
            'rec','expiry','weight','dimensions','param');
    
    protected $_dir;
    protected $_file;
    protected $_tmpFile;
    protected $_engine;
    
    public function run() {
        $this->beforeWrite();
        
        $this->writeShopInfo();
        $this->writeCurrencies();
        $this->writeCategories();
        $this->writeOffers();
        
        $this->afterWrite();
    }
    
    protected function beforeWrite() {
        if ($this->outputFile !== null) {
            $slashPos = strrpos($this->outputFile, DIRECTORY_SEPARATOR);
            if (false !== $slashPos) {
                $this->_file = substr($this->outputFile, $slashPos);
                $this->_dir = substr($this->outputFile, 0, $slashPos);
            }
            else {
               $this->_dir = ".";
            }
            $this->_tmpFile = $this->_dir.DIRECTORY_SEPARATOR.md5($this->_file);
        }
        else {
            $this->_tmpFile = 'php://output';
        }
        $engine = $this->getEngine();
        $engine->openURI($this->_tmpFile);
        if ($this->indentString) {
            $engine->setIndentString($this->indentString);
            $engine->setIndent(true);
        }
        $engine->startDocument('1.0',$this->encoding);
        $engine->startElement('yml_catalog');
        $engine->writeAttribute('date', date('Y-m-d H:i'));
        $engine->startElement('shop');
    }
    
    protected function afterWrite() {
        $engine = $this->getEngine();
        $engine->fullEndElement();
        $engine->fullEndElement();
        $engine->endDocument(); 
        
        if (null !== $this->outputFile)
            rename($this->_tmpFile, $this->outputFile);
    }
    
    protected function getEngine() {
        if (null === $this->_engine) {
            $this->_engine = new XMLWriter();
        }
        return $this->_engine;
    }

    protected function writeShopInfo() {
        $engine = $this->getEngine();
        foreach($this->shopInfo() as $elm=>$text) {
            if (in_array($elm,$this->shopInfoElements)) {
                $engine->writeElement($elm, $text);
            }
        }
    }
    
    protected function writeCurrencies() {
        $engine = $this->getEngine();
        $engine->startElement('currencies');
        $this->currencies();
        $engine->fullEndElement();
    }
    
    protected function writeCategories() {
        $engine = $this->getEngine();
        $engine->startElement('categories');
        $this->categories();
        $engine->fullEndElement();
    }
    
    protected function writeOffers() {
        $engine = $this->getEngine();
        $engine->startElement('offers');
        $this->offers();
        $engine->fullEndElement();
    }
    
    /**
     * Adds <currency> element. (See http://help.yandex.ru/partnermarket/currencies.xml)
     * @param string $id "id" attribute 
     * @param mixed $rate "rate" attribute
     */
    protected function addCurrency($id,$rate = 1) {
        $engine = $this->getEngine();
        $engine->startElement('currency');
        $engine->writeAttribute('id', $id);
        $engine->writeAttribute('rate', $rate);
        $engine->endElement();
    }
    
    /**
     * Adds <category> element. (See http://help.yandex.ru/partnermarket/categories.xml)
     * @param string $name category name
     * @param int $id "id" attribute
     * @param int $parentId "parentId" attribute
     */
    protected function addCategory($name,$id,$parentId = null) {
        $engine = $this->getEngine();
        $engine->startElement('category');
        $engine->writeAttribute('id', $id);
        if ($parentId)
            $engine->writeAttribute('parentId', $parentId);
        $engine->text($name);
        $engine->fullEndElement();
    }
    
    /**
     * Adds <offer> element. (See http://help.yandex.ru/partnermarket/offers.xml)
     * @param int $id "id" attribute
     * @param array $data array of subelements as elementName=>value pairs
     * @param array $params array of <param> elements. Every element is an array: array(NAME,UNIT,VALUE) (See http://help.yandex.ru/partnermarket/param.xml)
     * @param boolean $available "available" attribute
     * @param string $type "type" attribute
     * @param numeric $bid "bid" attribute
     * @param numeric $cbid "cbid" attribute
     */
    protected function addOffer($id,$data, $params = array(), $available=true, $type = 'vendor.model', $bid = null, $cbid = null) {
        $engine = $this->getEngine();
        $engine->startElement('offer');
        $engine->writeAttribute('id', $id);
        if ($type) 
            $engine->writeAttribute('type', $type);
        $engine->writeAttribute('available', $available ? 'true' : 'false');
        if ($bid) {
            $engine->writeAttribute('bid', $bid);
            if ($cbid) 
                $engine->writeAttribute('cbid', $cbid);
        }
        foreach($data as $elm=>$val) {
            if (in_array($elm,$this->offerElements)) {
                if (!is_array($val)) {
                    $val = array($val);
                }
                foreach($val as $value) {
                    $engine->writeElement($elm, $value);
                }
            }
        }
        foreach($params as $param) {
             $engine->startElement('param');
             $engine->writeAttribute('name', $param[0]);
             if ($param[1])
                 $engine->writeAttribute('unit', $param[1]);
             $engine->text($param[2]);
             $engine->endElement();
        }
        $engine->fullEndElement();
    }

    
    
    /* Methods that must be implemented in your custom derived class */     
    
    abstract protected function shopInfo();
    
    abstract protected function currencies();
    
    abstract protected function categories();
    
    abstract protected function offers();
}
