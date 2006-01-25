<?php
/**
* @package     jelix
* @subpackage  core
* @version     $Id$
* @author      Jouanneau Laurent
* @contributor
* @copyright   2005-2006 Jouanneau laurent
* @link        http://www.jelix.org
* @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*
*/

class jSelectorUrlHandler extends jSelectorClass {
    public $type = 'urlhandler';
    protected $_dirname = 'classes/';
    protected $_suffix = '.urlhandler.php';

}

/**
* Compilateur pour le moteur d'url significatifs
*/
class jUrlCompilerSignificant implements jISimpleCompiler{

    public function compile($aSelector){
        global $gJCoord;

        $sourceFile = $aSelector->getPath();
        $cachefile = $aSelector->getCompiledFilePath();


        // lecture du fichier xml
        $xml = simplexml_load_file ( $sourceFile);
        if(!$xml){
           return false;
        }
        /*
        <urls>
         <classicentrypoint name="index" default="true">
            <url path="/test/:mois/:annee" module="" action="">
                  <var name="mois" escape="true" regexp="\d{4}"/>
                  <var name="annee" escape="false" />
                  <staticvar name="bla" value="cequejeveux" />
            </url>
            <url handler="" module="" action=""  />
         </classicentrypoint>
        </urls>

         g�n�re dans un fichier propre � chaque entrypoint :

            $PARSE_URL = array($isDefault , $infoparser,$infoparser... )

            o�
            $isDefault : indique si c'est un point d'entr�e par d�faut, et donc si le parser ne trouve rien, si il ignore ou fait une erreur

            $infoparser = array('module','action','nom handler')
            ou
            $infoparser = array( 'module','action', 'regexp_pathinfo',
               array('annee','mois'), // tableau des valeurs dynamiques, class�es par ordre croissant
               array(true, false), // tableau des valeurs escapes
               array('bla'=>'cequejeveux' ) // tableau des valeurs statiques
            )


         g�n�re dans un fichier commun � tous :

            $CREATE_URL = array(
               'news~show@classic' =>
                  array(0,'entrypoint','handler')
                  ou
                  array(1,'entrypoint',
                        array('annee','mois','jour','id','titre'), // liste des param�tres de l'url � prendre en compte
                        array(true, false..), // valeur des escapes
                        "/news/%1/%2/%3/%4-%5", // forme de l'url
                        )
                  ou
                  array(2,'entrypoint'); pour les cl�s du type "@request" ou "module~@request"

        */
        $createUrlInfos=array();
        $createUrlContent="<?php \n";
        $defaultEntrypoints=array();
        $file = new jFile();
        foreach($xml->children() as $name=>$tag){
           if(!preg_match("/^(.*)entrypoint$/", $name,$m)){
               //TODO : erreur
               continue;
           }
           $requestType= $m[1];
           $entryPoint = (string)$tag['name'];
           $isDefault =  (isset($tag['default']) ? (((string)$tag['default']) == 'true'):false);
           $parseInfos = array($isDefault);

           if($isDefault){
             $createUrlInfos['@'.$requestType]=array(2,$entryPoint);
           }


           $parseContent = "<?php \n";
           foreach($tag->url as $url){
               $module = (string)$url['module'];

               // dans le cas d'un point d'entr�e qui est celui par defaut pour le type de requete indiqu�
               // si il y a juste un module indiqu� alors on sait que toutes les actions
               // concernant ce module passeront par ce point d'entr�e.
               if($isDefault && !isset($url['action']) && !isset($url['handler'])){
                 $parseInfos[]=array($module, '', '.*', array(), array(), array() );
                 $createUrlInfos[$module.'~@'.$requestType] = array(2,$entryPoint);
                 continue;
               }

               $action = (string)$url['action'];

               // si il y a un handler indiqu�, on sait alors que pour le module et action indiqu�
               // il faut passer par cette classe handler pour le parsing et la creation de l'url
               if(isset($url['handler'])){
                  $class = (string)$url['handler'];
                  $parseInfos[]=array($module, $action, $class );
                  $s= new jSelectorUrlHandler($module.'~'.$action);
                  $createUrlContent.="include_once('".$s->getPath()."');\n";
                  $createUrlInfos[$module.'~'.$action.'@'.$requestType] = array(0,$entryPoint, $class);
                  continue;
               }

               $listparam=array();
               $escapes = array();
               if(isset($url['path'])){
                  $path = (string)$url['path'];
                  $regexppath = $path;

                  if(preg_match_all("/\:([a-zA-Z]+)/",$path,$m, PREG_PATTERN_ORDER)){
                      $listparam=$m[1];

                      foreach($url->var as $var){

                        $nom = (string) $var['name'];
                        $k = array_search($nom, $listparam);
                        if($k === false){
                          // TODO error
                          continue;
                        }

                        if (isset ($var['escape'])){
                            $escapes[$k] = (((string)$var['escape']) == 'true');
                        }else{
                            $escapes[$k] = false;
                        }

                        if (isset ($var['regexp'])){
                            $regexp = '('.(string)$var['regexp'].')';
                        }else{
                            $regexp = '([^\/]+)';
                        }

                        $regexppath = str_replace(':'.$name, $regexp, $regexppath);
                      }
                  }
               }else{
                 $regexppath='.*';
                 $path='';
               }
               $liststatics = array();
               foreach($url->staticvar as $var){
                  $liststatics[(string)$var['name']] =(string)$var['value'];
               }
               $parseInfos[]=array($module, $action, $regexppath, $listparam, $escapes, $liststatics );
               $createUrlInfos[$module.'~'.$action.'@'.$requestType] = array(1,$entryPoint, $listparam, $escapes,$path);
           }

           $parseContent.='$GLOBALS[\'SIGNIFICANT_PARSEURL\'] = '.var_export($parseInfos, true).";\n?>";

           $file->write(JELIX_APP_TEMP_PATH.'compiled/urlsig/'.rawurlencode($entryPoint).'.entrypoint.php',$parseContent);
        }
        $createUrlContent .='$GLOBALS[\'SIGNIFICANT_CREATEURL\'] ='.var_export($createUrlInfos, true).";\n?>";
        $file->write(JELIX_APP_TEMP_PATH.'compiled/urlsig/creationinfos.php',$createUrlContent);
    }

}

?>
