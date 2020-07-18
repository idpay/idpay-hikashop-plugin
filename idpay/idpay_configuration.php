<?php
/**
 * IDPay payment plugin
 *
 * @developer JMDMahdi, vispa, mnbp1371
 * @publisher IDPay
 * @package VirtueMart
 * @subpackage payment
 * @copyright (C) 2020 IDPay
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * http://idpay.ir
 */
defined('_JEXEC') or die('Restricted access'); ?>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][api_key]"><?php
            echo JText::_('API KEY');
            ?></label>
    </td>
    <td>
        <input type="text" name="data[payment][payment_params][api_key]"
               value="<?php echo $this->escape(@$this->element->payment_params->api_key); ?>"/>
    </td>
</tr>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][sandbox]"><?php
            echo JText::_('آزمایشگاه');
            ?></label>
    </td>
    <td>
        <select name="data[payment][payment_params][sandbox]">
            <option value="yes"<?php echo(@$this->element->payment_params->sandbox == 'yes' ? 'selected="selected"' : ""); ?>>
                بله
            </option>
            <option value="no"<?php echo(@$this->element->payment_params->sandbox == 'no' ? 'selected="selected"' : ""); ?>>
                خیر
            </option>
        </select>
    </td>
</tr>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][success_message]"><?php
            echo JText::_('پیام پرداخت موفق');
            ?></label>
    </td>
    <td>
        <textarea type="text"
                  name="data[payment][payment_params][success_message]"><?php echo(!empty($this->escape(@$this->element->payment_params->success_message)) ? $this->escape(@$this->element->payment_params->success_message) : "پرداخت شما با موفقیت انجام شد. کد رهگیری: {track_id}"); ?></textarea>
        <br>
        متن پیامی که می خواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت کدهای
        {order_id} برای نمایش شماره سفارش و {track_id} برای نمایش کد رهگیری آیدی پی استفاده نمایید.
    </td>
</tr>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][failed_message]"><?php
            echo JText::_('پیام پرداخت ناموفق');
            ?></label>
    </td>
    <td>
        <textarea type="text"
                  name="data[payment][payment_params][failed_message]"><?php echo(!empty($this->escape(@$this->element->payment_params->failed_message)) ? $this->escape(@$this->element->payment_params->failed_massage) : "پرداخت شما ناموفق بوده است. لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید."); ?></textarea>
        <br>
        متن پیامی که می خواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت کدهای
        {order_id} برای نمایش شماره سفارش و {track_id} برای نمایش کد رهگیری آیدی پی استفاده نمایید.
    </td>
</tr>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][invalid_status]"><?php
            echo JText::_('INVALID_STATUS');
            ?></label>
    </td>
    <td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][invalid_status]', @$this->element->payment_params->invalid_status);
        ?></td>
</tr>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][pending_status]"><?php
            echo JText::_('PENDING_STATUS');
            ?></label>
    </td>
    <td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][pending_status]', @$this->element->payment_params->pending_status);
        ?></td>
</tr>
<tr>
    <td class="key">
        <label for="data[payment][payment_params][verified_status]"><?php
            echo JText::_('VERIFIED_STATUS');
            ?></label>
    </td>
    <td><?php
        echo $this->data['order_statuses']->display('data[payment][payment_params][verified_status]', @$this->element->payment_params->verified_status);
        ?></td>
</tr>