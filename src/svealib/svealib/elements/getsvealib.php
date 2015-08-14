<?php
/**
 *
 * @author Valérie Isaksen
 * @package VirtueMart
 * @copyright Copyright (c) 2004 - 2012 VirtueMart Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */
defined ('JPATH_BASE') or die();

/**
 * Renders a label element
 */


class JElementGetSvealib extends JElement {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $_name = 'getSvealib';
        var $sveaVersion = '2.4.20';

	function fetchElement ($name, $value, &$node, $control_name) {
                $update_url = "https://github.com/sveawebpay/virtuemart2-module/archive/master.zip";
                $url = "https://raw.githubusercontent.com/sveawebpay/virtuemart2-module/master/docs/info.json";
                $json = file_get_contents($url);
                $data = json_decode($json);
                $html .= "<div>Version: $this->sveaVersion</div><br />";
                $html .= "<div>";
                if($data->module_version <= $this->sveaVersion){
                    $html .= "You have the latest ". $this->sveaVersion . " version.";
                }elseif ($data->module_version > $this->sveaVersion) {
                    $html .= "UPDATE FOUND! <br />".$data->module_version;
                    $img = '<img src="'.JURI::root ().'/plugins/vmpayment/svealib/assets/images/download.png" height="56" widh="56" />';
                    $html .= "&nbsp;<br /><a href='$update_url'>$img</a>";
                }
                $html .= "</div>";
		return $html;
	}


}