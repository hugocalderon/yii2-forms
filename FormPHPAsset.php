<?php 
namespace pceuropa\forms;
/**
 * Asset bundle for render forms
 * @author Rafal Marguzewicz <info@pceuropa.net>
 * @version 1.4.1
 * @license MIT
 * https://github.com/pceuropa/yii2-forum
 * Please report all issues at GitHub
 * https://github.com/pceuropa/yii2-forum/issues
 */
class FormPHPAsset extends \yii\web\AssetBundle {
    public $sourcePath = '@vendor/pceuropa/yii2-forms/assets/form';
    public $baseUrl = '@web';
    public $js = [
        'js/forms/fp.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap\BootstrapAsset',
    ];
}
