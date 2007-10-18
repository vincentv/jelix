<?php
/**
 * @package     jelix
 * @subpackage  dao
 * @author      Laurent Jouanneau
 * @contributor Loic Mathaud
 * @copyright   2005-2007 Laurent Jouanneau
 * @copyright   2007 Loic Mathaud
 * @link        http://www.jelix.org
 * @licence     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
 */

/**
 * Base class for all record classes generated by the dao compiler
 * @package  jelix
 * @subpackage dao
 */
abstract class jDaoRecordBase {

    const ERROR_REQUIRED=1;
    const ERROR_BAD_TYPE=2;
    const ERROR_BAD_FORMAT=3;
    const ERROR_MAXLENGTH = 4;
    const ERROR_MINLENGTH = 5;

    /**
     * @return array informations on all properties
     * @see jDaoFactoryBase::getProperties()
     */
    abstract public function getProperties();

    /**
     * @return array list of properties name which contains primary keys
     * @see jDaoFactoryBase::getPrimaryKeyNames()
     * @since 1.0b3
     */
    abstract public function getPrimaryKeyNames();

    /**
     * check values in the properties of the record, according on the dao definition
     * @return array list of errors
     */
    public function check(){
        $errors=array();
        foreach($this->getProperties() as $prop=>$infos){
            $value = $this->$prop;

            // test required
            if($infos['required'] && $value === null){
                $errors[$prop][] = self::ERROR_REQUIRED;
                continue;
            }

            if($infos['datatype']=='varchar' || $infos['datatype']=='string'){
                if(!is_string($value) && $value !== null){
                    $errors[$prop][] = self::ERROR_BAD_TYPE;
                    continue;
                }
                // test regexp
                if ($infos['regExp'] !== null && preg_match ($infos['regExp'], $value) === 0){
                    $errors[$prop][] = self::ERROR_BAD_FORMAT;
                    continue;
                }

                //  test maxlength et minlength
                $len = iconv_strlen($value, $GLOBALS['gJConfig']->charset);
                if($infos['maxlength'] !== null && $len > intval($infos['maxlength'])){
                    $errors[$prop][] = self::ERROR_MAXLENGTH;
                }

                if($infos['minlength'] !== null && $len < intval($infos['minlength'])){
                    $errors[$prop][] = self::ERROR_MINLENGTH;
                }


            }elseif( in_array($infos['datatype'], array('int','integer','numeric', 'double', 'float'))) {
                // test datatype
                if($value !== null && !is_numeric($value)){
                    $errors[$prop][] = self::ERROR_BAD_TYPE;
                    continue;
                }
            }elseif( in_array($infos['datatype'], array('datetime', 'time','varchardate', 'date'))) {
                if (jLocale::timestampToDate ($value) === false){
                    $errors[$prop][] = self::ERROR_BAD_FORMAT;
                    continue;
                }
            }
        }
        return $errors;
    }

    /**
     * set values on the properties which correspond to the primary
     * key of the record
     * This method accept a single or many values as parameter
     */
    public function setPk(){
        $args=func_get_args();
        if(count($args)==1 && is_array($args[0])){
            $args=$args[0];
        }
        $pkf = $this->getPrimaryKeyNames();

        if(count($args) == 0 || count($args) != count($pkf) ) 
            throw new jException('jelix~dao.error.keys.missing');

        foreach($pkf as $k=>$prop){
           $this->$prop = $args[$k];
        }
        return true;
    }
    
    /**
     * return the value of fields corresponding to the primary key
     * @return mixed  the value or an array of values if there is several  pk
     * @since 1.0b3
     */
    public function getPk(){
        $pkf = $this->getPrimaryKeyNames();
        if(count($pkf) == 1){
            return $this->{$pkf[0]};
        }else{
            $list = array();
            foreach($pkf as $k=>$prop){
                $list[] = $this->$prop;
            }
            return $list;
        }
    }
}

?>