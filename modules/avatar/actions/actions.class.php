<?php

class avatarActions extends sfActions
{
	/** 
	 * ajax to upload
	 */
	public function executeUpload(sfWebRequest $request)
		{	
		// Chargement du helper Url
		sfProjectConfiguration::getActive()->loadHelpers('Asset'); // on utilise un helper ds thumb
		sfApplicationConfiguration::getActive()->loadHelpers('Thumb');
		
		$fichier=$error='';
		/**
		 * On cree le dossier necessaire
		 */
		if (!file_exists('uploads/avatars')){
			mkdir('uploads/avatars', 0777);
			mkdir('uploads/avatars/source', 0777);
			mkdir('uploads/avatars/thumb', 0777);
		}
		/**
		 *  On configure l'upload
		 */
		$Upload = new Upload();
		$Upload->Extension = '.jpg;.gif;.png;';
		$Upload->DirUpload ='uploads/avatars/source';
		$Upload->Required = true; //champ obligatoire
		$Upload->Filename=md5(strtotime('now').rand(1000000000,1000000000000));
		/**
		 * on envoi et on check si ya des erreurs
		 */
		if (!$Upload->Execute()){
			$message= $Upload-> GetError();
			$bug=1;
		}else{
			$bug=0;
			$infoFichier=$Upload-> GetSummary();
		}
		
		/**
		 * On genere notre message d'erreur
		 */
		if($bug==1){
			$alert="Des erreurs pour votre image on été détécté:";
			foreach ($message as $value){
				foreach ($value as $value2){
					$alert.="\n\n- ".(utf8_encode($value2));
				}
			}
			$alert.="\n\n";
			$alert.="Veuillez modifier votre fichier";
			$error=$alert;
		}else{
			/**
			 * On enregistre le nom du fichier et on cree la thumb
			 */
			$fichier=$infoFichier[1]['nom'];
			doThumb($fichier, 'avatars', $options = array('width'=>81, 'height'=>81), $resize = 'center', $default = '/sfWidgetFormAvatarPlugin/images/ico-add.png');
		}
		
		/** 
		 * on envoi sous forme json
		 */		
		sfConfig::set('sf_web_debug', false);
	  	return $this->renderText(json_encode(array('error' =>$error,'msg'=>$fichier)));
	}
}
