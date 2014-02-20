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
?>
<div id="svea_price_box" style="width: 75%;">
    <div id="svea_product_price_lowest"
         style="display:block;
                width: 88%;
                box-shadow: inset 10px 10px 10px -11px #d2d2d2;
                border-radius: 4px 4px 4px 4px;
                -moz-border-radius: 4px 4px 4px 4px;
                -webkit-border-radius: 4px 4px 4px 4px;
                border: 0.5px solid #bdbdbd;
                background-color: #ededed;
                overflow: hidden;
                position: relative;">
               <div style="
                    width:50%;
                    margin-left: auto;
                    margin-right: auto;
                    "><?php echo $viewData['logo']; ?>
               </div>
                <div style="
                    width:90%;
                    margin-left: auto;
                    margin-right: auto;
                    "><?php echo $viewData['line']; ?></div>
                  <div id="svea_arrow" style="
                    width:18%;
                    float: left;
                    margin: 7px 0px 3px 17px;
                    "><?php echo $viewData['arrow']; ?>
                  </div>
                <div style="
                    font-size: 11px;
                    color: #0c6b3f;
                    width:auto;
                    width:80%;
                    padding: 3px;
                    margin-left: auto;
                    margin-right: auto;"><?php echo $viewData['text_from']." ".$viewData['lowest_price'] ?>
                </div>
    </div>

         <div id="svea_product_price_all"
            style="
            display:none;
            float: left;
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
            overflow: hidden;
            position: absolute;
            padding: 3px 3px 0px 0px;
            ">

               <?php
               foreach ($viewData['price_list'] as $value) {
                    echo '<div class="svea_product_price_item" style="display:block;  list-style-position:outside; margin: 5px 10px 10px 10px">'.$value.'</div>';
                    echo $viewData['line'];
               }

               ?>

        </div>
    </div>
<script type="text/javascript">
     jQuery('#svea_arrow').hover(
     function (){
          jQuery("#svea_product_price_all").slideDown();
          jQuery(this).css({"cursor" : "pointer"});
    },
     function(){
           jQuery("#svea_product_price_all").css({"display" : "none"});
    });


</script>