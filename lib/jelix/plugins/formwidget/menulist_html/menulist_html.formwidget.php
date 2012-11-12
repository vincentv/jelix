<?php
/**
* @package     jelix
* @subpackage  forms
* @author      Claudio Bernardes
* @copyright   2012 Claudio Bernardes
* @link        http://www.jelix.org
* @licence     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
*/

/**
 * HTML form builder
 * @package     jelix
 * @subpackage  jelix-plugins
 * @link http://developer.jelix.org/wiki/rfc/jforms-controls-plugins
 */

class menulist_htmlFormWidget extends jFormsHtmlWidgetBuilder {
    function outputJs() {
        $ctrl = $this->ctrl;
        $jFormsJsVarName = $this->builder->getjFormsJsVarName();

        $this->builder->jsContent .= "c = new ".$jFormsJsVarName."ControlString('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";
        if ($ctrl instanceof jFormsControlDatasource
            && $ctrl->datasource instanceof jFormsDaoDatasource) {
            $dependentControls = $ctrl->datasource->getDependentControls();
            if ($dependentControls) {
                $this->builder->jsContent .="c.dependencies = ['".implode("','",$dependentControls)."'];\n";
                $this->builder->lastJsContent .= "jFormsJQ.tForm.declareDynamicFill('".$ctrl->ref."');\n";
            }
        }
        $this->commonJs($ctrl);
    }

    function outputControl() {
        $attr = $this->getControlAttributes();
        $value = $this->getValue($this->ctrl);

        unset($attr['readonly']);
        $attr['size'] = '1';
        echo '<select';
        $this->_outputAttr($attr);
        echo ">\n";

        if(is_array($value)){
            if(isset($value[0]))
                $value = $value[0];
            else
                $value='';
        }
        $value = (string) $value;
        echo '<option value=""',($value===''?' selected="selected"':''),'>',htmlspecialchars($this->ctrl->emptyItemLabel),"</option>\n";
        $this->fillSelect($this->ctrl, $value);
        echo '</select>';
    }
}
