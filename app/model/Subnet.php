<?php

namespace App\Model;

use Nette,
    Nette\Utils\Html;



/**
 * @author 
 */
class Subnet extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'Subnet';

    public function getSeznamSubnetu()
    {
        return($this->findAll());
    }

    public function getSubnet($id)
    {
        return($this->find($id));
    }
    public function deleteSubnet(array $subnets)
    {
		if(count($subnets)>0)
			return($this->delete(array('id' => $subnets)));
		else
			return true;
    }

    public function getSubnetForm(&$subnet) {	
		$subnet->addHidden('id')->setAttribute('class', 'id subnet');
		$subnet->addText('subnet', 'Subnet', 11)->setAttribute('class', 'subnet_text subnet')->setAttribute('placeholder', 'Subnet');
		$subnet->addText('gateway', 'Gateway', 11)->setAttribute('class', 'gateway_text subnet')->setAttribute('placeholder', 'Gateway');
		$subnet->addText('popis', 'Popis')->setAttribute('class', 'popis subnet')->setAttribute('placeholder', 'Popis');
    }    
    
    public function getSubnetTable($subnets) {
        $subnetyTab = Html::el('table')->setClass('table table-striped');
        $tr = $subnetyTab->create('tr');
        $tr->create('th')->setText('Subnet');
        $tr->create('th')->setText('Gateway');
        $tr->create('th')->setText('Popis');

        foreach ($subnets as $subnet) {
            $tr = $subnetyTab->create('tr');
            $tr->create('td')->setText($subnet->subnet);
            $tr->create('td')->setText($subnet->gateway);
            $tr->create('td')->setText($subnet->popis);
        } 
        return($subnetyTab);
    }
    
    /**
     * Najde subnet k zadané IP adrese
     * 
     * Malinko uprasené, prostě vygeneruju všechny možné subnety
     * a udělám WHERE subnet IN ...
     * 
     * @param string $ip IP adresa
     * @return string Subnet
     */
    public function getSubnetOfIP($ip) {
        $subnets = $this->genSubnets($ip);
        return($this->findAll()->where("subnet", $subnets));
    }
    
    /**
     * Generuje všechny možné subnety k IP adrese
     * 
     * Tvoří /16 až /32, víc není snad potřeba.
     * Používá se k dotazům do DB v getSubnetOfIP()
     * 
     * @param string $ip IP adresa
     * @return array Subnety
     */
    private function genSubnets($ip) {
        $lip = ip2long($ip);
        $subnets = array();
        for($mask = 32; $mask >= 16; $mask--) {
            $lmask = pow(2, 32 - $mask);
            $subnet = long2ip(floor($lip/$lmask)*$lmask);
            $subnets[] = $subnet."/".$mask;
        }
        return($subnets);
    }
    
    /**
     * Najde kolidující/překrývající-se subnety v DB
     * 
     * @param type $s Subnet
     * @return mixed(boolean/array) false pokud zadaný subnet nekoliduje, 
     *               pole kolidujících subnetů pokud koliduje
     */
    public function getOverlapingSubnet($s) {
        $posiblyColiding = $this->getPossiblyColiding($s);
        
        $coliding = array();
        foreach($posiblyColiding as $colSubnet) {
            if($this->checkColision($colSubnet->subnet, $s)) {
                $coliding[] = $colSubnet->subnet;
            }
        }
        
        if(count($coliding) > 0){
            return($coliding);
        }
        return(false);
    }
    
    /**
     * Vrátí subnety z DB se stejnou částí před CIDR modulo 8
     * 
     * příklad: při zadání 10.107.91.0/25 udělá dotaz
     * WHERE subnet LIKE 10.107.91.%/%
     * 
     * Používá se, protože DB neumí se subnety přímo pracovat.
     * 
     * @param type $s Subnet
     * @return Nette\Database\Table\ActiveRow Možné kolidující subnety
     */
    private function getPossiblyColiding($s) {
        list($network, $cidr) = explode("/", $s);
        $bigCidr = floor($cidr / 8);
        
        $bigNetwork = long2ip(ip2long($network) & ip2long($this->CIDRToMask($bigCidr*8)));
        
        $bigNetworkExploded = explode(".", $bigNetwork);
        for($i=0; $i<=3; $i++) {
            if($i >= $bigCidr) {
                $bigNetworkExploded[$i] = "%";
            }
        }
        
        $bigNetworkLike = implode(".", $bigNetworkExploded)."/%";
        
        return($this->findAll()->where("subnet LIKE ?", $bigNetworkLike));
    }
    
    /**
     * Zjistí, zda dva subnety kolidují
     * 
     * @param type $s1 Testovaný subnet 1
     * @param type $s2 Testovaný subnet 2
     * @return boolean false když subnety nekolidují, 
     *                 true  když kolidují.
     */
    private function checkColision($s1, $s2) {
        list($n1, $c1) = explode("/", $s1);
        list($n2, $c2) = explode("/", $s2);
        
        $first_1 = ip2long($n1);
        $last_1 = $first_1 + pow(2, 32 - $c1) - 1;
        
        $first_2 = ip2long($n2);
        $last_2 = $first_2 + pow(2, 32 - $c2) - 1;

        if(($first_1 < $first_2) && ($last_1 < $first_2)) {
            return(false);
        }
                
        if(($first_2 < $first_1) && ($last_2 < $first_1)) {
            return(false);
        }
        
        return(true);
    }
    
    /**
     * Validuje subnet (x.x.x.x/y)
     * 
     * 10.107.0.0/16 => true
     * 10.107.0.64/16 => false
     * 10.107.0.64/26 => true
     * 
     * @param string $subnet Validovaný subnet
     * @return boolean Výsledek validace
     */
    public function validateSubnet($subnet) {
        list($network, $cidr) = explode("/", $subnet);
        
        // Zkontroluje validitu adresy sítě
        if(!filter_var($ip, FILTER_VALIDATE_IP)) {
            return(false);
        }
        
        // Zkontroluje CIDR (0-32)
        if(!is_numeric($cidr) || $cidr < 0 || $cidr > 32) {
            return(false);
        }
        
        // Zkontroluje jestli opravdu jde o subnet a ne IP/maska
        $lnet = ip2long($network);
        $lmask = pow(2, 32 - $cidr);
        return(($lnet % $lmask) == 0);
    }
    
    /**
     * Převede CIDR (číslo za /) na masku
     * 
     * například 24 na 255.255.255.0
     * 
     * @param integer $cidr CIDR
     * @return string Maska
     */
    public function CIDRToMask($cidr) {
        return(long2ip(pow(2, 32) - pow(2, 32-$cidr)));
    }
    
}