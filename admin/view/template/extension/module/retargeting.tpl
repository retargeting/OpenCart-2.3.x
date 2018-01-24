<?php
/**
 * Retargeting Tracker for OpenCart 2.3.x
 *
 * admin/view/template/extension/module/retargeting.tpl
 */
?>
<?php echo $header; ?>
<?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <button type="submit" form="form-retargeting" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
            <h1><?php echo $heading_title; ?></h1>
            <ul class="breadcrumb">
                <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                <?php } ?>
            </ul>
        </div>
    </div>
    <div class="container-fluid">
        <?php if ($error_warning) { ?>
        <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>
        <div class="panel panel-default">

            <!-- Module inline title -->
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-paper-plane"></i> <?php echo $text_edit; ?></h3>
            </div>

            <div class="panel-body">

                <!-- Retargeting logo/img -->
                <div class="row" style="min-height:45px;">
                    <div class="col-md-12">
                        <img src="https://retargeting.ro/static/images/i/logo.png" class="img-responsive" alt="Retargeting Tracker for OpenCart 2.3.x" />
                    </div>
                </div>

                <!-- Sign up pop-up message -->
                <?php
                if (!isset($retargeting_apikey) && empty($retargeting_apikey)) { ?>
                    <div class="alert alert-info"><i class="fa fa-info-circle"></i> <?php echo $text_signup; ?></div>
                <?php } ?>
                
                <!-- Submission form -->
                <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-retargeting" class="form-horizontal">

                    <!-- API KEY -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-apikey"><?php echo $entry_apikey; ?></label>
                        <div class="col-sm-10">
                            <input type="text" name="retargeting_apikey" value="<?php echo $retargeting_apikey; ?>" placeholder="<?php echo $entry_apikey; ?>" id="input-apikey" class="form-control" />
                        </div>
                    </div>

                    <!-- API SECRET -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-token"><?php echo $entry_token; ?></label>
                        <div class="col-sm-10">
                            <input type="text" name="retargeting_token" value="<?php echo $retargeting_token; ?>" placeholder="<?php echo $entry_token; ?>" id="input-token" class="form-control" />
                        </div>
                    </div>

                    <!-- Enabled/Disabled status -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
                        <div class="col-sm-10">
                            <select name="retargeting_status" id="input-status" class="form-control">
                                    <option value="1" <?php echo ($retargeting_status === true) ? 'selected="selected"' : '' ?>><?php echo $text_enabled; ?></option>
                                    <option value="0" <?php echo ($retargeting_status === false ) ? 'selected="selected"' : '' ?>><?php echo $text_disabled; ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Layouts -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="retargeting_module">Assigned Layouts:</label>
                        <div class="col-sm-10">
                            <?php foreach ($layouts as $layout) { ?>
                            <span class="label label-default" name="<?php echo $layout['name']; ?>"><?php echo $layout['name']; ?></span>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- API URL -->
                    <hr />
                    
                    <!-- Recommendation Engine pop-up message -->
                    <div class="alert alert-info" id="retargeting-recomeng-pop"><i class="fa fa-info-circle"></i> <?php echo $text_layout; ?>
                        <button type="button" id="close-recomeng-pop" class="close" data-dismiss="alert">&times;</button>
                    </div>
                    <br>
                    <!-- Webshop Personalization -->
                    <div class="col-sm-2" style="color:forestgreen">
                        <h3>Webshop Personalization</h3>
                    </div>
                    <div class="col-sm-10">
                        <div class="well">
                                Allows the display of customized products carousel on your website pages. Please go to Layouts and add the Recommendation Engine modules for their respective page.
                                <i>i.e: 'Recommendation Engine Home Page' goes into 'Home' layer</i>
                        </div>
                    </div>

                    <!-- Recommendation Engine Enable Disable -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-recomeng"><?php echo $entry_recomeng; ?></label>
                        <div class="col-sm-10">
                            <select name="retargeting_recomeng" id="input-recomeng" class="form-control">
                                <option value="1" <?php echo ($retargeting_recomeng === true) ? 'selected="selected"' : '' ?>><?php echo $text_recomengEnabled; ?></option>
                                <option value="0" <?php echo ($retargeting_recomeng === false ) ? 'selected="selected"' : '' ?>><?php echo $text_recomengDisabled; ?></option>
                            </select>
                            <br>
                            <div class="alert alert-success" id="recomeng-response-msg" style="display: none"><b>Activated!</b>
                                <button type="button" id="close-recomeng-pop" class="close" data-dismiss="alert">&times;</button>
                            </div>
                        </div>
                    </div>
                    <br>
                    <hr>
                    <br>
                    <br>
                    <!-- Fine Tuning -->
                    <div class="col-sm-2" style="color:dodgerblue">
                        <h3>Fine tuning</h3>
                    </div>
                    <div class="col-sm-10">
                        <div class="well">
                            Your OpenCart theme may alterate certain CSS and HTML elements that are important for Retargeting. Below you can adjust the CSS selectors which the Retargeting App will be monitoring. A detailed documentation is available at <a href="https://retargeting.biz/admin?action=api_redirect&token=5ac66ac466f3e1ec5e6fe5a040356997" target="_blank">Retargeting: fine tuning</a>. Please use only single quotes. Example: input[type='text']
                        </div>
                    </div>

                    <!-- 1. setEmail -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-setEmail">Listen for e-mail input:</label>
                        <div class="col-sm-10">
                            <input type="text" name="retargeting_setEmail" value="<?php echo (isset($retargeting_setEmail) && !empty($retargeting_setEmail)) ? $retargeting_setEmail : 'input[type=\'text\']'; ?>" placeholder="" id="input-setEmail" class="form-control" />
                            <span class="small">setEmail</span>
                        </div>
                    </div>

                    <!-- 2. addToCart -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-addToCart">Add to cart button:</label>
                        <div class="col-sm-10">
                            <input type="text" name="retargeting_addToCart" value="<?php echo (isset($retargeting_addToCart) && !empty($retargeting_addToCart)) ? $retargeting_addToCart : '#button-cart'; ?>" placeholder="" id="input-addToCart" class="form-control" />
                            <span class="small">addToCart</span>
                        </div>
                    </div>

                    <!-- 3. clickImage -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-clickImage">Main image container:</label>
                        <div class="col-sm-10">
                            <input type="text" name="retargeting_clickImage" value="<?php echo (isset($retargeting_clickImage) && !empty($retargeting_clickImage)) ? $retargeting_clickImage : 'a.thumbnail'; ?>" placeholder="" id="input-clickImage" class="form-control" />
                            <span class="small">clickImage</span>
                        </div>
                    </div>

                    <!-- 4. commentOnProduct -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-commentOnProduct">Review/Comments button:</label>
                        <div class="col-sm-10">
                            <input type="text" name="retargeting_commentOnProduct" value="<?php echo (isset($retargeting_commentOnProduct) && !empty($retargeting_commentOnProduct)) ? $retargeting_commentOnProduct : '.button-review'; ?>" placeholder="" id="input-commentOnProduct" class="form-control" />
                            <span class="small">commentOnProduct</span>
                        </div>
                    </div>

                    <!-- 5. mouseOverPrice -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-mouseOverPrice">Product price container:</label>
                        <div class="col-sm-10">
                            <input type="text" name="retargeting_mouseOverPrice" value="<?php echo (isset($retargeting_mouseOverPrice) && !empty($retargeting_mouseOverPrice)) ? $retargeting_mouseOverPrice : 'ul > li > h2'; ?>" placeholder="" id="input-mouseOverPrice" class="form-control" />
                            <span class="small">mouseOverPrice</span>
                        </div>
                    </div>

                    <!-- 6. setVariation -->
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-setVariation">Product variation:</label>
                        <div class="col-sm-10">
                            <input type="text" name="retargeting_setVariation" value="<?php echo (isset($retargeting_setVariation) && !empty($retargeting_setVariation)) ? $retargeting_setVariation : ''; ?>" placeholder="" id="input-setVariation" class="form-control" />
                            <span class="small">setVariation</span>
                        </div>
                    </div>

                </form>

                <div class="row" style="padding-top: 25px;">
                    <hr />
                    <div class="col-sm-12">
                        <span class="small">You can get your <strong>Tracking API Key</strong> and <strong>REST API Key</strong> from your <a href="https://retargeting.biz/" target="_blank" rel="noopener noreferrer">Retargeting account</a>.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function() {
        jQuery("#input-recomeng").on("change", function() {
            var state = $(this).val();

            if (state == 1) {
                var data = {'action': 'insert'};
                recommendationEngineSettings(data);
            } else {
                var data = {'action': 'delete'};
                recommendationEngineSettings(data);
            }
        });
    });

    function recommendationEngineSettings(data) {
        jQuery.ajax({
            type: 'post',
            url: 'index.php?route=<?php echo $route ?>/ajax&token=<?php echo $token; ?>',
            data: data,
            dataType: 'json',
            success: function(json) {
                console.log(json.state);
                if (json.state) {
                    jQuery("#recomeng-response-msg").show();
                } else {
                    jQuery("#recomeng-response-msg").hide();
                }
            }, 
            error: function(e) {
                console.log('Error 01: Recommendation Engine AJAX call failed! Please contact us at info@retargeting.biz');
                console.log(e);
            }
        });
    }


// Dismiss Recommendation Engine pop-up message
jQuery(document).ready(function() {
	jQuery("#close-recomeng-pop").on('click', function() {
		sessionStorage.setItem('_ra_hidden', 'true');
	});

	var retSession = sessionStorage.getItem('_ra_hidden');
	if ( retSession  ) {
		jQuery("#retargeting-recomeng-pop").hide();
	}
});
</script>
