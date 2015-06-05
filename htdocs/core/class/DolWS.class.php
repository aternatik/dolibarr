<?php
/* Copyright (C) 2015 Jean-François Ferry             <jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Class for Dolibarr webservices
 *
 * @author jfefe
 */
class DolWS
{
    /**
     *
     * @var string  Namespace 
     */
    public $ns='http://www.dolibarr.org/ns/';
    
    /**
     *
     * @var string  Request encoding 
     */
    public $encoding='UTF-8';
       
    /**
     *
     * @var SoapServer      SoapServerObject 
     */
    private $soap;
    
    /**
     * Contructor method
     */
    public function __construct()
    {
        $this->soap = new SoapServer(
            null,
            array(
                'uri' => $this->ns,
                'encoding' => $this->encoding 
                //'features' => SOAP_USE_XSI_ARRAY_TYPE + SOAP_SINGLE_ELEMENT_ARRAYS
            )
        );
    }

    /**
     * Add Class to soap server
     * 
     * @param type $classname
     */
    public function addClass($classname) {
       $this->soap->setClass($classname);
    }
    
    /**
     * Process SOAP request
     * 
     */
    public function processRequest() {
        $this->soap->handle();
    }
    
    /**
     * 
     * @param array $array  Datas
     * @return type
     */
    static public function array_to_object(array $array)
    {
        foreach($array as $key => $value)
        {
            if(is_array($value))
            {
                $array[$key] = self::array_to_object($value);
            }
        }
        return (object)$array;
    }
}
