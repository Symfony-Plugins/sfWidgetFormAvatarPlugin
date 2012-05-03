<?php


class sfWidgetFormAvatar extends sfWidgetFormInputHidden
{
  /**
   * Gets the Stylesheets paths associated with the widget.
   *
   * @return array An array of Stylesheets paths
   */
 public function getStylesheets()
  {
    return array('/sfWidgetFormAvatarPlugin/css/avatar.css' => 'all');
  }

  /**
   * Gets the JavaScript paths associated with the widget.
   *
   * @return array An array of JavaScript paths
   */
  public function getJavascripts()
  {
    return array('http://code.jquery.com/jquery-latest.min.js', '/sfWidgetFormAvatarPlugin/js/ajaxfileupload.js');
	
  }
   
  
  protected function configure($options = array(), $attributes = array())
  {
    parent::configure($options, $attributes);
  }

  public function render($name, $value = null, $attributes = array(), $errors = array())
  {	
    $html = parent::render($name, $value, $attributes, $errors);
    
    if ($value){
    	$bgimg='/uploads/avatars/thumb/81x81_'.$value;
    }
    else{
    	$bgimg='/sfWidgetFormAvatarPlugin/images/ico-add.png';
    }

    $html .= "
    <img src=\"/sfWidgetFormAvatarPlugin/images/loading.gif\" title=\"Loading\" alt=\"Loading\" style=\"display:none;\" id=\"loading_".$this->generateId($name)."\"/>
    <div class=\"inputfile\" style=\"margin-top:0px; border:2px solid #fff; cursor:pointer; background-image:url(".$bgimg.");\" id=\"imginput_".$this->generateId($name)."\">
    	<input type=\"hidden\" name=\"MAX_FILE_SIZE[]\" value=\"\" />
       	<input type=\"file\" name=\"userfile[]\"  class=\"input_avatar min\" id=\"fichier_".$this->generateId($name)."\" onchange=\"return ajaxFileUpload('".$this->generateId($name)."');\"/>
    </div> 

   	<script type=\"text/javascript\">
   	//<![CDATA[
    var url_for_avatar='".url_for('avatar/upload')."';
    //]]>
   	</script>";

    return $html;
  }
}