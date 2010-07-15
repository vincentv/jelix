<?php
/**
* @package     jelix
* @subpackage  forms
* @author      Laurent Jouanneau
* @contributor Julien Issler, Dominique Papin
* @copyright   2006-2010 Laurent Jouanneau
* @copyright   2008 Julien Issler, 2008 Dominique Papin
* @link        http://www.jelix.org
* @licence     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
*/

/**
 * HTML form builder
 * @package     jelix
 * @subpackage  jelix-plugins
 */
class jFormsBuilderHtml extends jFormsBuilderBase {

    protected $jFormsJsVarName = 'jForms';

    protected $options;

    protected $isRootControl = true;

    public function outputAllControls() {

        echo '<table class="jforms-table" border="0">';
        foreach( $this->_form->getRootControls() as $ctrlref=>$ctrl){
            if($ctrl->type == 'submit' || $ctrl->type == 'reset' || $ctrl->type == 'hidden') continue;
            if(!$this->_form->isActivated($ctrlref)) continue;
            if($ctrl->type == 'group') {
                echo '<tr><td colspan="2">';
                $this->outputControl($ctrl);
                echo '</td></tr>';
            }else{
                echo '<tr><th scope="row">';
                $this->outputControlLabel($ctrl);
                echo '</th><td>';
                $this->outputControl($ctrl);
                echo "</td></tr>\n";
            }
        }
        echo '</table> <div class="jforms-submit-buttons">';
        if ( $ctrl = $this->_form->getReset() ) {
            if(!$this->_form->isActivated($ctrl->ref)) continue;
            $this->outputControl($ctrl);
            echo ' ';
        }
        foreach( $this->_form->getSubmits() as $ctrlref=>$ctrl){
            if(!$this->_form->isActivated($ctrlref)) continue;
            $this->outputControl($ctrl);
            echo ' ';
        }
        echo "</div>\n";
    }

    public function outputMetaContent($t) {
        global $gJCoord, $gJConfig;
        $resp= $gJCoord->response;
        if($resp === null || $resp->getType() !='html'){
            return;
        }
        $www =$gJConfig->urlengine['jelixWWWPath'];
        $bp =$gJConfig->urlengine['basePath'];
        $resp->addJSLink($www.'js/jforms_light.js');
        $resp->addCSSLink($www.'design/jform.css');
        foreach($t->_vars as $k=>$v){
            if($v instanceof jFormsBase && count($edlist = $v->getHtmlEditors())) {
                foreach($edlist as $ed) {
                    if(isset($gJConfig->htmleditors[$ed->config.'.engine.file'])){
                        if(is_array($gJConfig->htmleditors[$ed->config.'.engine.file'])){
                            foreach($gJConfig->htmleditors[$ed->config.'.engine.file'] as $url) {
                                $resp->addJSLink($bp.$url);
                            }
                        }else
                            $resp->addJSLink($bp.$gJConfig->htmleditors[$ed->config.'.engine.file']);
                    }
                    if(isset($gJConfig->htmleditors[$ed->config.'.config']))
                        $resp->addJSLink($bp.$gJConfig->htmleditors[$ed->config.'.config']);
                    $skin = $ed->config.'.skin.'.$ed->skin;
                    if(isset($gJConfig->htmleditors[$skin]) && $gJConfig->htmleditors[$skin] != '')
                        $resp->addCSSLink($bp.$gJConfig->htmleditors[$skin]);
                }
            }
        }
    }

    protected function outputHeaderScript(){
                echo '<script type="text/javascript">
//<![CDATA[
'.$this->jFormsJsVarName.'.tForm = new jFormsForm(\''.$this->_name.'\');
'.$this->jFormsJsVarName.'.tForm.setErrorDecorator(new '.$this->options['errorDecorator'].'());
'.$this->jFormsJsVarName.'.declareForm(jForms.tForm);
//]]>
</script>';
    }

    /**
     * output the header content of the form
     * @param array $params some parameters <ul>
     *      <li>"errDecorator"=>"name of your javascript object for error listener"</li>
     *      <li>"method" => "post" or "get". default is "post"</li>
     *      </ul>
     */
    public function outputHeader($params){
        $this->options = array_merge(array('errorDecorator'=>$this->jFormsJsVarName.'ErrorDecoratorHtml',
            'method'=>'post'), $params);

        if (preg_match('#^https?://#',$this->_action)) {
            $urlParams = $this->_actionParams;
            echo '<form action="',$this->_action,'" method="'.$this->options['method'].'" id="', $this->_name,'"';
        } else {
            $url = jUrl::get($this->_action, $this->_actionParams, 2); // retourne le jurl correspondant
            $urlParams = $url->params;
            echo '<form action="',$url->getPath(),'" method="'.$this->options['method'].'" id="', $this->_name,'"';
        }
        if($this->_form->hasUpload())
            echo ' enctype="multipart/form-data">';
        else
            echo '>';

        $this->outputHeaderScript();

        $hiddens = '';
        foreach ($urlParams as $p_name => $p_value) {
            $hiddens .= '<input type="hidden" name="'. $p_name .'" value="'. htmlspecialchars($p_value). '"'.$this->_endt. "\n";
        }

        foreach ($this->_form->getHiddens() as $ctrl) {
            if(!$this->_form->isActivated($ctrl->ref)) continue;
            $hiddens .= '<input type="hidden" name="'. $ctrl->ref.'" id="'.$this->_name.'_'.$ctrl->ref.'" value="'. htmlspecialchars($this->_form->getData($ctrl->ref)). '"'.$this->_endt. "\n";
        }

        if($this->_form->securityLevel){
            $tok = $this->_form->createNewToken();
            $hiddens .= '<input type="hidden" name="__JFORMS_TOKEN__" value="'.$tok.'"'.$this->_endt. "\n";
        }

        if($hiddens){
            echo '<div class="jforms-hiddens">',$hiddens,'</div>';
        }

        $errors = $this->_form->getContainer()->errors;
        if(count($errors)){
            $ctrls = $this->_form->getControls();
            echo '<ul id="'.$this->_name.'_errors" class="jforms-error-list">';
            $errRequired='';
            foreach($errors as $cname => $err){
                if(!$this->_form->isActivated($ctrls[$cname]->ref)) continue;
                if($err == jForms::ERRDATA_REQUIRED) {
                    if($ctrls[$cname]->alertRequired){
                        echo '<li>', $ctrls[$cname]->alertRequired,'</li>';
                    }else{
                        echo '<li>', jLocale::get('jelix~formserr.js.err.required', $ctrls[$cname]->label),'</li>';
                    }
                }elseif ($err != '' && $err != jForms::ERRDATA_INVALID) {
                    echo '<li>', $err,'</li>';
                }else{
                    if($ctrls[$cname]->alertInvalid){
                        echo '<li>', $ctrls[$cname]->alertInvalid,'</li>';
                    }else{
                        echo '<li>', jLocale::get('jelix~formserr.js.err.invalid', $ctrls[$cname]->label),'</li>';
                    }
                }

            }
            echo '</ul>';
        }
    }

    protected $jsContent = '';

    protected $lastJsContent = '';

    public function outputFooter(){
        echo '<script type="text/javascript">
//<![CDATA[
(function(){var c, c2;
'.$this->jsContent.$this->lastJsContent.'
})();
//]]>
</script>';
        echo '</form>';
    }

    public function outputControlLabel($ctrl){
        if($ctrl->type == 'hidden' || $ctrl->type == 'group') return;
        $required = ($ctrl->required == false || $ctrl->isReadOnly()?'':' jforms-required');
        $inError = (isset($this->_form->getContainer()->errors[$ctrl->ref]) ?' jforms-error':'');
        $hint = ($ctrl->hint == ''?'':' title="'.htmlspecialchars($ctrl->hint).'"');
        $id = $this->_name.'_'.$ctrl->ref;
        $idLabel = ' id="'.$id.'_label"';
        if($ctrl->type == 'output' || $ctrl->type == 'checkboxes' || $ctrl->type == 'radiobuttons' || $ctrl->type == 'date' || $ctrl->type == 'datetime'){
            echo '<span class="jforms-label',$required,$inError,'"',$idLabel,$hint,'>',htmlspecialchars($ctrl->label),"</span>\n";
        }else if($ctrl->type != 'submit' && $ctrl->type != 'reset'){
            echo '<label class="jforms-label',$required,$inError,'" for="',$id,'"',$idLabel,$hint,'>',htmlspecialchars($ctrl->label),"</label>\n";
        }
    }

    public function outputControl($ctrl, $attributes=array()){
        if($ctrl->type == 'hidden') return;
        $ro = $ctrl->isReadOnly();
        $attributes['name'] = $ctrl->ref;
        $attributes['id'] = $this->_name.'_'.$ctrl->ref;

        if ($ro)
            $attributes['readonly'] = 'readonly';
        else
            unset($attributes['readonly']);
        if (!isset($attributes['title']) && $ctrl->hint) {
            $attributes['title'] = $ctrl->hint;
        }

        $class = 'jforms-ctrl-'.$ctrl->type;
        $class .= ($ctrl->required == false || $ro?'':' jforms-required');
        $class .= (isset($this->_form->getContainer()->errors[$ctrl->ref]) ?' jforms-error':'');
        $class .= ($ro && $ctrl->type != 'captcha'?' jforms-readonly':'');
        if (isset($attributes['class']))
            $attributes['class'].= ' '.$class;
        else
            $attributes['class'] = $class;
        $this->{'output'.$ctrl->type}($ctrl, $attributes);
        echo "\n";
        $this->{'js'.$ctrl->type}($ctrl);
        $this->outputHelp($ctrl);
    }

    protected function _outputAttr(&$attributes) {
        foreach($attributes as $name=>$val) {
            echo ' '.$name.'="'.htmlspecialchars($val).'"';
        }
    }

    protected function escJsStr($str) {
        return '\''.str_replace(array("'","\n"),array("\\'", "\\n"), $str).'\'';
    }

    protected function commonJs($ctrl) {

        if($ctrl->required){
            $this->jsContent .="c.required = true;\n";
            if($ctrl->alertRequired){
                $this->jsContent .="c.errRequired=".$this->escJsStr($ctrl->alertRequired).";\n";
            }
            else {
                $this->jsContent .="c.errRequired=".$this->escJsStr(jLocale::get('jelix~formserr.js.err.required', $ctrl->label)).";\n";
            }
        }

        if($ctrl->alertInvalid){
            $this->jsContent .="c.errInvalid=".$this->escJsStr($ctrl->alertInvalid).";\n";
        }
        else {
            $this->jsContent .="c.errInvalid=".$this->escJsStr(jLocale::get('jelix~formserr.js.err.invalid', $ctrl->label)).";\n";
        }

        if ($this->isRootControl) $this->jsContent .= $this->jFormsJsVarName.".tForm.addControl(c);\n";
    }

    protected function outputInput($ctrl, &$attr) {
        $value = $this->_form->getData($ctrl->ref);
        if ($ctrl->size != 0)
            $attr['size'] = $ctrl->size;
        $maxl= $ctrl->datatype->getFacet('maxLength');
        if($maxl !== null)
            $attr['maxlength']=$maxl;
        $attr['value'] = $value;
        $attr['type'] = 'text';
        echo '<input';
        $this->_outputAttr($attr);
        echo $this->_endt;
    }

    protected function jsInput($ctrl) {

        $datatype = array('jDatatypeBoolean'=>'Boolean','jDatatypeDecimal'=>'Decimal','jDatatypeInteger'=>'Integer','jDatatypeHexadecimal'=>'Hexadecimal',
                        'jDatatypeDateTime'=>'Datetime','jDatatypeDate'=>'Date','jDatatypeTime'=>'Time',
                        'jDatatypeUrl'=>'Url','jDatatypeEmail'=>'Email','jDatatypeIPv4'=>'Ipv4','jDatatypeIPv6'=>'Ipv6');
        $isLocale = false;
        $data_type_class = get_class($ctrl->datatype);
        if(isset($datatype[$data_type_class]))
            $dt = $datatype[$data_type_class];
        else if ($ctrl->datatype instanceof jDatatypeLocaleTime)
            { $dt = 'Time'; $isLocale = true; }
        else if ($ctrl->datatype instanceof jDatatypeLocaleDate)
            { $dt = 'LocaleDate'; $isLocale = true; }
        else if ($ctrl->datatype instanceof jDatatypeLocaleDateTime)
            { $dt = 'LocaleDatetime'; $isLocale = true; }
        else
            $dt = 'String';

        $this->jsContent .="c = new ".$this->jFormsJsVarName."Control".$dt."('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";
        if ($isLocale)
            $this->jsContent .="c.lang='".$GLOBALS['gJConfig']->locale."';\n";

        $maxl= $ctrl->datatype->getFacet('maxLength');
        if($maxl !== null)
            $this->jsContent .="c.maxLength = '$maxl';\n";

        $minl= $ctrl->datatype->getFacet('minLength');
        if($minl !== null)
            $this->jsContent .="c.minLength = '$minl';\n";

        $this->commonJs($ctrl);
    }

    protected function _outputDateControlDay($ctrl, $attr, $value){
        $attr['name'] = $ctrl->ref.'[day]';
        $attr['id'] .= 'day';
        if($GLOBALS['gJConfig']->forms['controls.datetime.input'] == 'textboxes'){
            $attr['value'] = $value;
            echo '<input type="text" size="2" maxlength="2"';
            $this->_outputAttr($attr);
            echo $this->_endt;
        }
        else{
            echo '<select';
            $this->_outputAttr($attr);
            echo '><option value="">'.htmlspecialchars(jLocale::get('jelix~jforms.date.day.label')).'</option>';
            for($i=1;$i<32;$i++){
                $k = ($i<10)?'0'.$i:$i;
                echo '<option value="'.$k.'"'.($k == $value?' selected="selected"':'').'>'.$k.'</option>';
            }
            echo '</select>';
        }
    }

    protected function _outputDateControlMonth($ctrl, $attr, $value){
        $attr['name'] = $ctrl->ref.'[month]';
        $attr['id'] .= 'month';
        if($GLOBALS['gJConfig']->forms['controls.datetime.input'] == 'textboxes') {
            $attr['value'] = $value;
            echo '<input type="text" size="2" maxlength="2"';
            $this->_outputAttr($attr);
            echo $this->_endt;
        }
        else{
            $monthLabels = $GLOBALS['gJConfig']->forms['controls.datetime.months.labels'];
            echo '<select';
            $this->_outputAttr($attr);
            echo '><option value="">'.htmlspecialchars(jLocale::get('jelix~jforms.date.month.label')).'</option>';
            for($i=1;$i<13;$i++){
                $k = ($i<10)?'0'.$i:$i;
                if($monthLabels == 'names')
                    $l = htmlspecialchars(jLocale::get('jelix~date_time.month.'.$k.'.label'));
                else if($monthLabels == 'shortnames')
                    $l = htmlspecialchars(jLocale::get('jelix~date_time.month.'.$k.'.shortlabel'));
                else
                    $l = $k;
                echo '<option value="'.$k.'"'.($k == $value?' selected="selected"':'').'>'.$l.'</option>';
            }
            echo '</select>';
        }
    }

    protected function _outputDateControlYear($ctrl, $attr, $value){
        $attr['name'] = $ctrl->ref.'[year]';
        $attr['id'] .= 'year';
        if($GLOBALS['gJConfig']->forms['controls.datetime.input'] == 'textboxes') {
            $attr['value'] = $value;
            echo '<input type="text" size="4" maxlength="4"';
            $this->_outputAttr($attr);
            echo $this->_endt;
        }
        else{
            $minDate = $ctrl->datatype->getFacet('minValue');
            $maxDate = $ctrl->datatype->getFacet('maxValue');
            if($minDate && $maxDate){
                echo '<select';
                $this->_outputAttr($attr);
                echo '><option value="">'.htmlspecialchars(jLocale::get('jelix~jforms.date.year.label')).'</option>';
                for($i=$minDate->year;$i<=$maxDate->year;$i++)
                    echo '<option value="'.$i.'"'.($i == $value?' selected="selected"':'').'>'.$i.'</option>';
                echo '</select>';
            }
            else{
                $attr['value'] = $value;
                echo '<input type="text" size="4" maxlength="4"';
                $this->_outputAttr($attr);
                echo $this->_endt;
            }
        }
    }

    protected function _outputDateControlHour($ctrl, $attr, $value){
        $attr['name'] = $ctrl->ref.'[hour]';
        $attr['id'] .= 'hour';
        if($GLOBALS['gJConfig']->forms['controls.datetime.input'] == 'textboxes') {
            $attr['value'] = $value;
            echo '<input type="text" size="2" maxlength="2"';
            $this->_outputAttr($attr);
            echo $this->_endt;
        }
        else{
            echo '<select';
            $this->_outputAttr($attr);
            echo '><option value="">'.htmlspecialchars(jLocale::get('jelix~jforms.time.hour.label')).'</option>';
            for($i=0;$i<24;$i++){
                $k = ($i<10)?'0'.$i:$i;
                echo '<option value="'.$k.'"'.( (string) $k === $value?' selected="selected"':'').'>'.$k.'</option>';
            }
            echo '</select>';
        }
    }

    protected function _outputDateControlMinutes($ctrl, $attr, $value){
        $attr['name'] = $ctrl->ref.'[minutes]';
        $attr['id'] .= 'minutes';
        if($GLOBALS['gJConfig']->forms['controls.datetime.input'] == 'textboxes') {
            $attr['value'] = $value;
            echo '<input type="text" size="2" maxlength="2"';
            $this->_outputAttr($attr);
            echo $this->_endt;
        }
        else{
            echo '<select';
            $this->_outputAttr($attr);
            echo '><option value="">'.htmlspecialchars(jLocale::get('jelix~jforms.time.minutes.label')).'</option>';
            for($i=0;$i<60;$i++){
                $k = ($i<10)?'0'.$i:$i;
                echo '<option value="'.$k.'"'.( (string) $k === $value?' selected="selected"':'').'>'.$k.'</option>';
            }
            echo '</select>';
        }
    }

    protected function _outputDateControlSeconds($ctrl, $attr, $value){
        $attr['name'] = $ctrl->ref.'[seconds]';
        $attr['id'] .= 'seconds';
        if(!$ctrl->enableSeconds) 
            echo '<input type="hidden" id="'.$attr['id'].'" name="'.$attr['name'].'" value="'.$value.'"'.$this->_endt;
        else if($GLOBALS['gJConfig']->forms['controls.datetime.input'] == 'textboxes') {
            $attr['value'] = $value;
            echo '<input type="text"';
            $this->_outputAttr($attr);
            echo $this->_endt;
        }
        else{
            echo '<select';
            $this->_outputAttr($attr);
            echo '><option value="">'.htmlspecialchars(jLocale::get('jelix~jforms.time.seconds.label')).'</option>';
            for($i=0;$i<60;$i++){
                $k = ($i<10)?'0'.$i:$i;
                echo '<option value="'.$k.'"'.( (string) $k === $value?' selected="selected"':'').'>'.$k.'</option>';
            }
            echo '</select>';
        }
    }

    protected function outputDate($ctrl, &$attr){
        $attr['id'] = $this->_name.'_'.$ctrl->ref.'_';
        $v = array('year'=>'','month'=>'','day'=>'');
        if(preg_match('#^(\d{4})?-(\d{2})?-(\d{2})?$#',$this->_form->getData($ctrl->ref),$matches)){
            if(isset($matches[1]))
                $v['year'] = $matches[1];
            if(isset($matches[2]))
                $v['month'] = $matches[2];
            if(isset($matches[3]))
                $v['day'] = $matches[3];
        }
        $f = jLocale::get('jelix~format.date');
        for($i=0;$i<strlen($f);$i++){
            if($f[$i] == 'Y')
                $this->_outputDateControlYear($ctrl, $attr, $v['year']);
            else if($f[$i] == 'm')
                $this->_outputDateControlMonth($ctrl, $attr, $v['month']);
            else if($f[$i] == 'd')
                $this->_outputDateControlDay($ctrl, $attr, $v['day']);
            else
                echo ' ';
        }
    }

    protected function jsDate($ctrl){
        $this->jsContent .= "c = new ".$this->jFormsJsVarName."ControlDate('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";
        $this->jsContent .= "c.multiFields = true;\n";
        $minDate = $ctrl->datatype->getFacet('minValue');
        $maxDate = $ctrl->datatype->getFacet('maxValue');
        if($minDate)
            $this->jsContent .= "c.minDate = '".$minDate->toString(jDateTime::DB_DFORMAT)."';\n";
        if($maxDate)
            $this->jsContent .= "c.maxDate = '".$maxDate->toString(jDateTime::DB_DFORMAT)."';\n";
        $this->commonJs($ctrl);
    }

    protected function outputDatetime($ctrl, &$attr){
        $attr['id'] = $this->_name.'_'.$ctrl->ref.'_';
        $v = array('year'=>'','month'=>'','day'=>'','hour'=>'','minutes'=>'','seconds'=>'');
        if(preg_match('#^(\d{4})?-(\d{2})?-(\d{2})? (\d{2})?:(\d{2})?(:(\d{2})?)?$#',$this->_form->getData($ctrl->ref),$matches)){
            if(isset($matches[1]))
                $v['year'] = $matches[1];
            if(isset($matches[2]))
                $v['month'] = $matches[2];
            if(isset($matches[3]))
                $v['day'] = $matches[3];
            if(isset($matches[4]))
                $v['hour'] = $matches[4];
            if(isset($matches[5]))
                $v['minutes'] = $matches[5];
            if(isset($matches[7]))
                $v['seconds'] = $matches[7];
        }
        $f = jLocale::get('jelix~format.datetime');
        for($i=0;$i<strlen($f);$i++){
            if($f[$i] == 'Y')
                $this->_outputDateControlYear($ctrl, $attr, $v['year']);
            else if($f[$i] == 'm')
                $this->_outputDateControlMonth($ctrl, $attr, $v['month']);
            else if($f[$i] == 'd')
                $this->_outputDateControlDay($ctrl, $attr, $v['day']);
            else if($f[$i] == 'H')
                $this->_outputDateControlHour($ctrl, $attr, $v['hour']);
            else if($f[$i] == 'i')
                $this->_outputDateControlMinutes($ctrl, $attr, $v['minutes']);
            else if($f[$i] == 's')
                $this->_outputDateControlSeconds($ctrl, $attr, $v['seconds']);
            else
                echo ' ';
        }
    }

    protected function jsDatetime($ctrl){
        $this->jsContent .= "c = new ".$this->jFormsJsVarName."ControlDatetime('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";
        $this->jsContent .= "c.multiFields = true;\n";
        $minDate = $ctrl->datatype->getFacet('minValue');
        $maxDate = $ctrl->datatype->getFacet('maxValue');
        if($minDate)
            $this->jsContent .= "c.minDate = '".$minDate->toString(jDateTime::DB_DTFORMAT)."';\n";
        if($maxDate)
            $this->jsContent .= "c.maxDate = '".$maxDate->toString(jDateTime::DB_DTFORMAT)."';\n";
        $this->commonJs($ctrl);
    }

    protected function outputCheckbox($ctrl, &$attr) {
        $value = $this->_form->getData($ctrl->ref);

        if($ctrl->valueOnCheck == $value){
            $attr['checked'] = "checked";
         }
        $attr['value'] = $ctrl->valueOnCheck;
        $attr['type'] = 'checkbox';
        echo '<input';
        $this->_outputAttr($attr);
        echo $this->_endt;
    }

    protected function jsCheckbox($ctrl) {

        $this->jsContent .="c = new ".$this->jFormsJsVarName."ControlBoolean('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";

        $this->commonJs($ctrl);
    }

    protected function outputCheckboxes($ctrl, &$attr) {
        $i=0;
        $id = $this->_name.'_'.$ctrl->ref.'_';
        $attrs = ' name="'.$ctrl->ref.'[]" id="'.$id;
        $value = $this->_form->getData($ctrl->ref);
        $attr['name'] = $ctrl->ref.'[]';
        unset($attr['title']);
        if(is_array($value) && count($value) == 1)
            $value = $value[0];
        $span ='<span class="jforms-chkbox jforms-ctl-'.$ctrl->ref.'"><input type="checkbox"';

        if(is_array($value)){
            $value = array_map(create_function('$v', 'return (string) $v;'),$value);
            foreach($ctrl->datasource->getData($this->_form) as $v=>$label){
                $attr['id'] = $id.$i;
                $attr['value'] = $v;
                echo $span;
                $this->_outputAttr($attr);
                if(in_array((string) $v,$value,true))
                    echo ' checked="checked"';
                echo $this->_endt,'<label for="',$id,$i,'">',htmlspecialchars($label),"</label></span>\n";
                $i++;
            }
        }else{
            $value = (string) $value;
            foreach($ctrl->datasource->getData($this->_form) as $v=>$label){
                $attr['id'] = $id.$i;
                $attr['value'] = $v;
                echo $span;
                $this->_outputAttr($attr);
                if((string) $v === $value)
                    echo ' checked="checked"';
                echo $this->_endt,'<label for="',$id,$i,'">',htmlspecialchars($label),"</label></span>\n";
                $i++;
            }
        }
    }

    protected function jsCheckboxes($ctrl) {

        $this->jsContent .="c = new ".$this->jFormsJsVarName."ControlString('".$ctrl->ref."[]', ".$this->escJsStr($ctrl->label).");\n";

        $this->commonJs($ctrl);
    }

    protected function outputRadiobuttons($ctrl, &$attr) {
        $i=0;
        $id = $this->_name.'_'.$ctrl->ref.'_';
        $attr['name'] = $ctrl->ref;
        unset($attr['title']);
        $value = $this->_form->getData($ctrl->ref);
        if(is_array($value)){
            if(isset($value[0]))
                $value = $value[0];
            else
                $value = '';
        }
        $value = (string) $value;
        $span ='<span class="jforms-radio jforms-ctl-'.$ctrl->ref.'"><input type="radio"';
        foreach($ctrl->datasource->getData($this->_form) as $v=>$label){
            $attr['id'] = $id.$i;
            $attr['value'] = $v;
            echo $span;
            $this->_outputAttr($attr);
            echo ((string) $v===$value?' checked="checked"':''),$this->_endt;
            echo '<label for="',$this->_name,'_',$ctrl->ref,'_',$i,'">',htmlspecialchars($label),"</label></span>\n";
            $i++;
        }
    }

    protected function jsRadiobuttons($ctrl) {

        $this->jsContent .="c = new ".$this->jFormsJsVarName."ControlString('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";

        $this->commonJs($ctrl);
    }

    protected function outputMenulist($ctrl, &$attr) {
        unset($attr['readonly']);
        $attr['size'] = '1';
        echo '<select';
        $this->_outputAttr($attr);
        echo ">\n";
        $value = $this->_form->getData($ctrl->ref);
        if(is_array($value)){
            if(isset($value[0]))
                $value = $value[0];
            else
                $value='';
        }
        $value = (string) $value;
        if (!$ctrl->required) {
            echo '<option value=""',($value===''?' selected="selected"':''),'>',htmlspecialchars($ctrl->emptyItemLabel),"</option>\n";
        }
        foreach($ctrl->datasource->getData($this->_form) as $v=>$label){
            echo '<option value="',htmlspecialchars($v),'"',((string) $v===$value?' selected="selected"':''),'>',htmlspecialchars($label),"</option>\n";
        }
        echo '</select>';
    }

    protected function jsMenulist($ctrl) {

        $this->jsContent .="c = new ".$this->jFormsJsVarName."ControlString('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";

        $this->commonJs($ctrl);
    }

    protected function outputListbox($ctrl, &$attr) {
        unset($attr['readonly']);
        $attr['size'] = $ctrl->size;

        if($ctrl->multiple){
            $attr['name'] = $ctrl->ref.'[]';
            $attr['id'] = $this->_name.'_'.$ctrl->ref;
            $attr['multiple'] = 'multiple';
            echo '<select';
            $this->_outputAttr($attr);
            echo ">\n";
            $value = $this->_form->getData($ctrl->ref);

            if(is_array($value) && count($value) == 1)
                $value = $value[0];

            if(is_array($value)){
                $value = array_map(create_function('$v', 'return (string) $v;'),$value);
                foreach($ctrl->datasource->getData($this->_form) as $v=>$label){
                    echo '<option value="',htmlspecialchars($v),'"',(in_array((string) $v,$value,true)?' selected="selected"':''),'>',htmlspecialchars($label),"</option>\n";
                }
            }else{
                $value = (string) $value;
                foreach($ctrl->datasource->getData($this->_form) as $v=>$label){
                    echo '<option value="',htmlspecialchars($v),'"',((string) $v===$value?' selected="selected"':''),'>',htmlspecialchars($label),"</option>\n";
                }
            }
            echo '</select>';
        }else{
            $value = $this->_form->getData($ctrl->ref);

            if(is_array($value)){
                if(count($value) >= 1)
                    $value = $value[0];
                else
                    $value ='';
            }

            $value = (string) $value;
            echo '<select';
            $this->_outputAttr($attr);
            echo ">\n";
            foreach($ctrl->datasource->getData($this->_form) as $v=>$label){
                echo '<option value="',htmlspecialchars($v),'"',((string) $v===$value?' selected="selected"':''),'>',htmlspecialchars($label),"</option>\n";
            }
            echo '</select>';
        }
    }

    protected function jsListbox($ctrl) {
        if($ctrl->multiple){
            $this->jsContent .= "c = new ".$this->jFormsJsVarName."ControlString('".$ctrl->ref."[]', ".$this->escJsStr($ctrl->label).");\n";
            $this->jsContent .= "c.multiple = true;\n";
        } else {
            $this->jsContent .= "c = new ".$this->jFormsJsVarName."ControlString('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";
        }

        $this->commonJs($ctrl);
    }

    protected function outputTextarea($ctrl, &$attr) {
        if (!isset($attr['rows']))
            $attr['rows'] = $ctrl->rows;
        if (!isset($attr['cols']))
            $attr['cols'] = $ctrl->cols;
        echo '<textarea';
        $this->_outputAttr($attr);
        echo '>',htmlspecialchars($this->_form->getData($ctrl->ref)),'</textarea>';
    }

    protected function jsTextarea($ctrl) {
        $this->jsContent .="c = new ".$this->jFormsJsVarName."ControlString('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";

        $maxl= $ctrl->datatype->getFacet('maxLength');
        if($maxl !== null)
            $this->jsContent .="c.maxLength = '$maxl';\n";

        $minl= $ctrl->datatype->getFacet('minLength');
        if($minl !== null)
            $this->jsContent .="c.minLength = '$minl';\n";

        $this->commonJs($ctrl);
    }

    protected function outputHtmleditor($ctrl, &$attr) {
        $this->outputTextarea($ctrl, $attr);
    }

    protected function jsHtmleditor($ctrl) {
        $this->jsTextarea($ctrl);
        $engine = $GLOBALS['gJConfig']->htmleditors[$ctrl->config.'.engine.name'];
        $this->jsContent .= 'jelix_'.$engine.'_'.$ctrl->config.'("'.$this->_name.'_'.$ctrl->ref.'","'.$this->_name."\");\n";
    }

    protected function outputWikieditor($ctrl, &$attr) {
        $this->outputTextarea($ctrl, $attr);
    }

    protected function jsWikieditor($ctrl) {

    }

    protected function outputSecret($ctrl, &$attr) {
        if ($ctrl->size != 0)
            $attr['size'] = $ctrl->size;
        $maxl = $ctrl->datatype->getFacet('maxLength');
        if($maxl !== null)
            $attr['maxlength'] = $maxl;
        $attr['type'] = 'password';
        $attr['value'] = $this->_form->getData($ctrl->ref);
        echo '<input';
        $this->_outputAttr($attr);
        echo $this->_endt;
    }

    protected function jsSecret($ctrl) {
        $this->jsContent .="c = new ".$this->jFormsJsVarName."ControlSecret('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";

        $maxl= $ctrl->datatype->getFacet('maxLength');
        if($maxl !== null)
            $this->jsContent .="c.maxLength = '$maxl';\n";

        $minl= $ctrl->datatype->getFacet('minLength');
        if($minl !== null)
            $this->jsContent .="c.minLength = '$minl';\n";

        $this->commonJs($ctrl);
    }

    protected function outputSecretconfirm($ctrl, &$attr) {
        if ($ctrl->size != 0)
            $attr['size'] = $ctrl->size;
        $attr['type'] = 'password';
        $attr['value'] = $this->_form->getData($ctrl->ref);
        echo '<input';
        $this->_outputAttr($attr);
        echo $this->_endt;
    }

    protected function jsSecretconfirm($ctrl) {
        $this->jsContent .="c = new ".$this->jFormsJsVarName."ControlConfirm('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";
        $this->commonJs($ctrl);
    }

    protected function outputOutput($ctrl, &$attr) {
        unset($attr['readonly']);
        unset($attr['class']);
        if (isset($attr['title'])){
            $hint = ' title="'.htmlspecialchars($attr['title']).'"';
            unset($attr['title']);
        }
        else $hint = '';
        $attr['type'] = 'hidden';
        $attr['value'] = $this->_form->getData($ctrl->ref);
        echo '<input';
        $this->_outputAttr($attr);
        echo $this->_endt;
        echo '<span class="jforms-value"',$hint,'>',htmlspecialchars($attr['value']),'</span>';
    }

    protected function jsOutput($ctrl) {
    }

    protected function outputUpload($ctrl, &$attr) {
        if($ctrl->maxsize){
            echo '<input type="hidden" name="MAX_FILE_SIZE" value="',$ctrl->maxsize,'"',$this->_endt;
        }
        $attr['type'] = 'file';
        $attr['value'] = '';
        echo '<input';
        $this->_outputAttr($attr);
        echo $this->_endt;
    }

    protected function jsUpload($ctrl) {
        $this->jsContent .="c = new ".$this->jFormsJsVarName."ControlString('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";

        $this->commonJs($ctrl);
    }

    protected function outputSubmit($ctrl, $attr) {
        unset($attr['readonly']);
        $attr['class'] = 'jforms-submit';
        $attr['type'] = 'submit';

        if($ctrl->standalone){
            $attr['value'] = $ctrl->label;
            echo '<input';
            $this->_outputAttr($attr);
            echo $this->_endt;
        }else{
            $id = $this->_name.'_'.$ctrl->ref.'_';
            $attr['name'] = $ctrl->ref;
            foreach($ctrl->datasource->getData($this->_form) as $v=>$label){
                // because IE6 sucks with <button type=submit> (see ticket #431), we must use input :-(
                $attr['value'] = $label;
                $attr['id'] = $id.$v;
                echo ' <input';
                $this->_outputAttr($attr);
                echo $this->_endt;
            }
        }
    }

    protected function jsSubmit($ctrl) {
        // no javascript
    }

    protected function outputReset($ctrl, &$attr) {
        unset($attr['readonly']);
        $attr['class'] = 'jforms-reset';
        $attr['type'] = 'reset';
        echo '<button';
        $this->_outputAttr($attr);
        echo '>',htmlspecialchars($ctrl->label),'</button>';
    }

    protected function jsReset($ctrl) {
        // no javascript
    }

    protected function outputCaptcha($ctrl, &$attr) {
        $ctrl->initExpectedValue();
        echo '<span class="jforms-captcha-question">',htmlspecialchars($ctrl->question),'</span> ';

        unset($attr['readonly']);
        $attr['type'] = 'text';
        $attr['value'] = '';
        echo '<input';
        $this->_outputAttr($attr);
        echo $this->_endt;
    }

    protected function jsCaptcha($ctrl) {
        $this->jsTextarea($ctrl);
    }

    protected function outputGroup($ctrl, &$attr) {
        echo '<fieldset><legend>',htmlspecialchars($ctrl->label),"</legend>\n";
        echo '<table class="jforms-table-group" border="0">',"\n";
        foreach( $ctrl->getChildControls() as $ctrlref=>$c){
            if($c->type == 'submit' || $c->type == 'reset' || $c->type == 'hidden') continue;
            if(!$this->_form->isActivated($ctrlref)) continue;
            echo '<tr><th scope="row">';
            $this->outputControlLabel($c);
            echo "</th>\n<td>";
            $this->outputControl($c);
            echo "</td></tr>\n";
        }
        echo "</table></fieldset>";
    }

    protected function jsGroup($ctrl) {
        //no javacript
    }

    protected function outputChoice($ctrl, &$attr) {
        echo '<ul class="jforms-choice jforms-ctl-'.$ctrl->ref.'" >',"\n";

        $value = $this->_form->getData($ctrl->ref);
        if(is_array($value)){
            if(isset($value[0]))
                $value = $value[0];
            else
                $value='';
        }

        $i=0;
        $attr['name'] = $ctrl->ref;
        $id = $this->_name.'_'.$ctrl->ref.'_';
        $attr['type']='radio';
        unset($attr['class']);
        $readonly = (isset($attr['readonly']) && $attr['readonly']!='');

        $this->jsChoiceInternal($ctrl);
        $this->jsContent .="c2 = c;\n";
        $this->isRootControl = false;
        foreach( $ctrl->items as $itemName=>$listctrl){
            echo '<li><label><input';
            $attr['id'] = $id.$i;
            $attr['value'] = $itemName;
            if ($itemName==$value)
                $attr['checked'] = 'checked';
            else
                unset($attr['checked']);
            $this->_outputAttr($attr);
            echo ' onclick="'.$this->jFormsJsVarName.'.getForm(\'',$this->_name,'\').getControl(\'',$ctrl->ref,'\').activate(\'',$itemName,'\')"', $this->_endt;
            echo htmlspecialchars($ctrl->itemsNames[$itemName]),"</label>\n";

            $displayedControls = false;
            foreach($listctrl as $ref=>$c) {
                if(!$this->_form->isActivated($ref) || $c->type == 'hidden') continue;
                $displayedControls = true;
                echo ' <span class="jforms-item-controls">';
                // we remove readonly status so when a user change the choice and
                // javascript is deactivated, it can still change the value of the control
                $ro = $c->isReadOnly();
                if($ro && !$readonly) $c->setReadOnly(false);
                $this->outputControlLabel($c);
                echo ' ';
                $this->outputControl($c);
                if($ro) $c->setReadOnly(true);
                echo "</span>\n";
                $this->jsContent .="c2.addControl(c, ".$this->escJsStr($itemName).");\n";
            }
            if(!$displayedControls) {
                $this->jsContent .="c2.items[".$this->escJsStr($itemName)."]=[];\n";
            }

            echo "</li>\n";
            $i++;
        }
        echo "</ul>\n";
        $this->isRootControl = true;
    }

    protected function jsChoice($ctrl) {
        $value = $this->_form->getData($ctrl->ref);
        if(is_array($value)){
            if(isset($value[0]))
                $value = $value[0];
            else
                $value='';
        }
        $this->jsContent .= "c2.activate('".$value."');\n";
    }

    protected function jsChoiceInternal($ctrl) {

        $this->jsContent .="c = new ".$this->jFormsJsVarName."ControlChoice('".$ctrl->ref."', ".$this->escJsStr($ctrl->label).");\n";

        $this->commonJs($ctrl);
    }

    protected function outputHelp($ctrl) {
        if ($ctrl->help) {
            if($ctrl->type == 'checkboxes' || ($ctrl->type == 'listbox' && $ctrl->multiple)){
                $name=$ctrl->ref.'[]';
            }else{
                $name=$ctrl->ref;
            }
            echo '<span class="jforms-help" id="'. $this->_name.'-help"><span>'.htmlspecialchars($ctrl->help).'</span></span>';
        }
    }
}