<div class="container">
	<form class="" method="post" action="" name="forgotform" id="forgotform">
		<div class="row form-group">
			<div class="col-xs-3">
				<label class="control-label" for="email">Электронная почта <sup class="text-danger">*</sup></label>
			</div>
			<div class="col-xs-9">
				<input class="form-control" type="text" id="email" name="email" value="<?= $email ?>" placeholder="mail@mail.ru">
				<? if($error_email) { ?>
					<div class="text-danger">
						<?= $error_email ?>
					</div>
				<? } ?>
			</div>
		</div>
		<div class="row form-group">
			<div class="col-xs-3">
				<label class="control-label" for="captcha">Проверочный код <sup class="text-danger">*</sup></label>
			</div>
			<div class="col-xs-9">
				<input class="form-control" type="text" id="captcha" name="captcha">
				<? if($error_captcha) { ?>
					<div class="text-danger">
						<?= $error_captcha ?>
					</div>
				<? } ?>
				<img src="assets/captcha" alt="captcha" width="120px" height="60px"/></div>
		</div>
		<hr>
		<div class="row form-group">
			<div class="col-xs-3">&nbsp;</div>
			<div class="col-xs-9 text-right">
				<a href="<?= $controllerLogin ?>" class="btn btn-primary">Войти
					<i class="fa fa-sign-in"></i>
				</a>
				<a href="<?= $controllerRegister ?>" class="btn btn-primary">Зарегистрироваться
					<i class="fa fa-edit"></i>
				</a>
				<button class="btn btn-success" type="submit" name="action" value="forgot">Напомнить пароль
					<i class="fa fa-unlock"></i>
				</button>
			</div>
		</div>
	</form>
</div>
<script type="text/javascript">
	$(document).ready(function() {

		var config = <?php echo $json_config ?>;

		$('#forgotform :submit').click(function(e) {
			e.preventDefault();

			var form = $(this).closest('form'),
				params = 'action=' + $(this).val() + '&' + $.param(config) + '&' + form.serialize();

			$.ajax({
				url: 'ajax.php?route=account/controller/forgot/ajax',
				dataType: 'json',
				type: 'post',
				data: params,
				beforeSend: function() {
					form.fadeTo(150, 0.5);
				},
				success: function(json) {
					form.fadeTo(150, 1);
					$('.has-error').removeClass('has-error');
					$('div.text-danger').remove();

					if(json['redirect']) {
						location = json['redirect'];
					} else if(json['error']) {
						for(i in json['error']) {
							var $field = $('[name="' + i + '"]', form);
							$field.closest('.form-group').addClass('has-error');
							$field.closest('div').append('<div class="text-danger">' + json['error'][i] + '</div>');
						}
					}
				},
				error: function(xhr, ajaxOptions, thrownError) {
					alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
				}
			});
		});
	});
</script>