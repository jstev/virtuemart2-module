<?phpdefined('_JEXEC') or die('Restricted access');
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
<div id="svea_price_box" style="width: 80%;">
    <div id="svea_product_price_lowest"
         style="display:block;
                width: 80%;
                outline: 0,5px solid #B5B5B5;
                box-shadow: inset 10px 10px 10px -11px #d2d2d2;
                border-radius: 4px 4px 4px 4px;
                -moz-border-radius: 4px 4px 4px 4px;
                -webkit-border-radius: 4px 4px 4px 4px;
                border: 0.5px solid #bdbdbd;
                background-color: #ededed;
                overflow: hidden;">
                <div id="svea_arrow" style="width:auto; float: left; margin-top: 18px;"><?php echo $viewData['arrow']; ?></div>
                <div style="width:auto; float: left; "><?php echo $viewData['logo']; ?></div>

                <div style="width:auto; float: left; margin: 2px 7px;"><?php echo $viewData['lowest_price'] ?></div>




    </div>

         <div id="svea_product_price_all"
            style="
            display:none;
            float: left;
            width: 80%;
            max-width: 200px;
            padding: 5px;
            outline: 0,5px solid #B5B5B5;
            box-shadow: inset 10px 10px 10px -11px #d2d2d2;
            border-radius: 4px 4px 4px 4px;
            -moz-border-radius: 4px 4px 4px 4px;
            -webkit-border-radius: 4px 4px 4px 4px;
            background-color: #ededed;
            border: 0.5px solid #bdbdbd;
            z-index: 10;
            overflow: hidden;
            position: absolute;
            padding: 10px;
            ">
             <ul style="
                 list-style-position: inside;
                 list-style-type: circle;">
               <?php
               foreach ($viewData['price_list'] as $value) {
                    echo '<li class="svea_product_price_item" style="display:block;  margin-bottom: 8px">- '.$value.'</li>';
               }

               ?>
             </ul>
        </div>
    </div>
<script type="text/javascript">
     jQuery('#svea_arrow').hover(
     function (){
          jQuery("#svea_product_price_all").slideDown();
    },
     function(){
           jQuery("#svea_product_price_all").css({"display" : "none"});
    });


</script>