<?php
/**
 * RayPay payment plugin
 *
 * @developer     hanieh729
 * @publisher     RayPay
 * @package       J2Store
 * @subpackage    payment
 * @copyright (C) 2021 RayPay
 * @license       http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * https://raypay.ir
 */
defined( '_JEXEC' ) or die( 'Restricted access' );
?>

<form method="get" name="adminForm" enctype="multipart/form-data">
    <p>
        <img src="/plugins/j2store/payment_raypay/payment_raypay/logo.png" style="display: inline-block;vertical-align: middle;width: 70px;">
        <?php echo "پرداخت امن با رای پی"; ?>
    </p>
    <br/>
    <?php if(!empty(@$vars->error)): ?>
        <div class="warning alert alert-danger">
            <?php echo @$vars->error?>
        </div>
    <?php else:?>
        <input type="submit" class="j2store_cart_button button btn btn-primary"
               value="<?php echo 'پرداخت'; ?>"/>
    <?php endif; ?>
</form>
