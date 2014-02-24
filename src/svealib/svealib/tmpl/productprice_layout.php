<?php defined('_JEXEC') or die('Restricted access');
/**
 * @version $Id: productprice_layout.php 6510 2012-10-08 11:26:10Z alatak $
 *
 * @author Valérie Isaksen
 * @package VirtueMart
 * @copyright Copyright (C) 2012 iStraxx - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */
//JHTML::stylesheet('style.css', JURI::root(TRUE) . '/plugins/vmpayment/svealib/assets/css/', false); //do not work
//$document = JFactory::getDocument();
$data = $viewData['svea_paymentplan'] == NULL ? $viewData['svea_invoice'] : $viewData['svea_paymentplan'];
if($viewData['svea_paymentplan'] != NULL && $viewData['svea_invoice'] != NULL){
    $lowest_price = $viewData['svea_paymentplan']['lowest_price'] < $viewData['svea_invoice']['lowest_price'] ? $viewData['svea_paymentplan']['lowest_price'] : $viewData['svea_invoice']['lowest_price'];
    $currency_display = $viewData['svea_paymentplan']['lowest_price'] < $viewData['svea_invoice']['lowest_price'] ? $viewData['svea_paymentplan']['currency_display'] : $viewData['svea_invoice']['currency_display'];

}else{
    $lowest_price = $data['lowest_price'];
    $currency_display = $data['currency_display'];
}

?>

<div id="svea_price_box"
       style=" width: auto;
                height: 40px;
                margin: 0 0 15px;
               display:block;">
     <div style="position:relative; z-index:1; display:block;" >
        <div id="svea_product_price_lowest"
                            style="display:block; overflow: hidden;">
        <div style="
             float: left;
             display: block;
             width:50px;
             margin-left: auto;
             margin-right: auto;
             "><img width="170"
               style="position:absolute;
                     z-index:1;"
                     src="<?php echo JURI::root(TRUE) ?>/plugins/vmpayment/svealib/assets/images/svea/svea_background.png" />
        </div>
         <div id="svea_price_arrow" style="display:block;">
             <div id="svea_arrow" style="
                display: block;
                width:auto;
                position:absolute;
                z-index:2;
                left: -2px;
                top:26px;
               margin: 7px -10px 3px 17px;
               "><img src="<?php echo JURI::root(TRUE) ?>/plugins/vmpayment/svealib/assets/images/svea/blue_arrow.png" />
            </div>
            <div style="
                 display: block;
                position:absolute;
                 z-index:2;
                 left:36px;
                 top:26px;
                color: #002A46;
                width:auto;
                padding: 3px;
                margin-left: auto;
                margin-right: auto;"><?php echo $data['text_from']." ".$lowest_price. $currency_display; ?>
            </div>
        </div>

         <div id="svea_product_price_all"
            style="
            display:none;
            width: 100%;
            max-width: 206px;
            padding: 5px;
            box-shadow: inset 10px 10px 10px -11px #d2d2d2;
            border-radius: 4px 4px 4px 4px;
            -moz-border-radius: 4px 4px 4px 4px;
            -webkit-border-radius: 4px 4px 4px 4px;
            background-color: #ededed;
            border: 0.5px solid #bdbdbd;
            z-index: 10;
            position: absolute;
            top:50px;
            padding: 3px 3px 0px 0px;
            ">

               <?php
               if ($viewData['svea_paymentplan'] != null) {
                   foreach ($viewData['svea_paymentplan']['price_list'] as $value) {
                    echo $value;
                    echo '<div style="width:90%;
                                    margin-left: auto;
                                    margin-right: auto;">'.$data['line'].
                        '</div>';
                    }
               }
               if($viewData['svea_invoice'] != null){
                   foreach ($viewData['svea_invoice']['price_list'] as $value) {
                    echo $value;
                    echo '<div style="width:90%;
                                    margin-left: auto;
                                    margin-right: auto;">'.$data['line'].
                        '</div>';
                    }
               }
               ?>
             </div>
         </div>
    </div>
</div>

<script type="text/javascript">
       jQuery(document).ready(function () {

                jQuery("#svea_price_arrow").hover(function (){
                     jQuery(this).css({"cursor" : "pointer"});
                });
                jQuery("#svea_product_price_all").click(function (){
                     jQuery("#svea_product_price_all").slideUp();
                });
                jQuery("#svea_price_arrow").toggle(
                    function (){
                         jQuery("#svea_product_price_all").slideDown();
                         jQuery(this).css({"cursor" : "pointer"});
                   },
                    function(){
                         jQuery("#svea_product_price_all").slideUp();
                   });

               });
</script>