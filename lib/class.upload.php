<?php ini_set('display_errors','off');
/**
 * @version   2.2a, derni�re r�vision le 17 mars 2008
 * @author    Olivier VEUJOZ
 * 
 * Modifications version 2.2a
 *   - Correction du param�trage par d�faut de la propri�t� $Permission (string => integer)
 * 
 * Modifications version 2.2 :
 *   - Ajout des derniers messages d'erreurs retourn�s par PHP (UPLOAD_ERR_EXTENSION, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_NO_TMP_DIR)
 *   - Ajout de la propri�t� 'octets' dans le tableau des informations sur un fichier (taille du fichier en octets)
 *   - Ajout du test sur la pr�sence du tableau $_FILES
 *   - Modifications mineures sur la logique de la classe (fonction checkUpload() et sur la fonction de nettoyage du nom de fichier, qui supprime tout caract�re de fichier Windows invalide.
 * 
 * SECURITY CONSIDERATION: If you are saving all uploaded files to a directory accesible with an URL, remember to filter files not only by mime-type (e.g. image/gif), but also by extension. The mime-type is reported by the client, if you trust him, he can upload a php file as an image and then request it, executing malicious code. 
 * I hope I am not giving hackers a good idea anymore than I am giving it to good-intended developers. Cheers.
 * Some restrictive firewalls may not let file uploads happen via a form with enctype="multipart/form-data".
 * We were having problems with an upload script hanging (not returning content) when a file was uploaded through a remote office firewall. Removing the enctype parameter of the form allowed the form submit to happen but then broke the file upload capability. Everything but the file came through. Using a dial-in or other Internet connection (bypassing the bad firewall) allowed everything to function correctly.
 * So if your upload script does not respond when uploading a file, it may be a firewall issue.
 * 
 * Compatibilit� :
 *  - compatible safe_mode
 *  - compatible open_basedir pour peu que les droits sur le r�pertoire temporaire d'upload soient allou�s
 *  - Version minimum de php : 5.x
 * 
 * Par d�faut :
 *  - autorise tout type de fichier
 *  - autorise les fichier allant jusqu'� la taille maximale sp�cifi�e dans le php.ini
 *  - envoie le(s) fichier(s) dans le r�pertoire de la classe
 *  - n'affiche qu'un champ de type file
 *  - permet de laisser les champs de fichiers vides
 *  - �crase le fichier s'il existe d�j�
 *  - n'ex�cute aucune v�rification
 *  - utilise les ent�tes renvoy�s par le navigateur pour v�rifier le type mime.
 * 
 * Notes :
 *  - le chemin de destination peut �tre d�fini en absolu ou en relatif
 *  - set_time_limit n'a pas d'effet lorsque PHP fonctionne en mode safe mode . Il n'y a pas d'autre solution que de changer de mode, ou de modifier la dur�e maximale d'ex�cution dans le php.ini
 *  - Int�gration depuis la version 2.0b des fonctions Mimetype de php (http://fr3.php.net/manual/fr/ref.mime-magic.php).
 * 
 * Notes sur l'int�gration des fonctions MimeType de PHP:
 *      - PHP doit �tre compil� avec l'option --enable-mime-magic. Sous Windows, il suffit de s'assurer de l'existence de la dll php_mime_magic.dll et de l'activer dans le php.ini
 *      - D�clarer ensuite une nouvelle section dans votre php.ini et renseignez l� comme suit :
 *          [MIME_MAGIC]
 *          ;PHP_INI_SYSTEM Disponible depuis PHP 5.0.0. 
 *          mime_magic.debug = 0
 *          ;PHP_INI_SYSTEM Disponible depuis PHP 4.3.0. 
 *          mime_magic.magicfile = "$PHP_INSTALL_DIR\magic.mime" o� $PHP_INSTALL_DIR fait r�f�rence �  votre chemin jusqu'� l'ex�cutable PHP
 *      - Le fichier magic.mime n'est pas fourni avec PHP. Il est t�l�chargeable http://gnuwin32.sourceforge.net/packages/file.htm (dans l'arborescence \share\file\)
 *        Il est recommand� de le copier � la racine de l'ex�cutable PHP. (�tape n�cessaire sous windows, pas s�r pour les autres OS)
 * 
 * Notes sur l'installation de la librairie PECL plateforme windows
 *      - T�l�charger la collection de modules PECL depuis la page de t�l�chargement g�n�ral de PHP en ad�quation avec la version de PHP utilis�e ("Collection of PECL modules", http://www.php.net/downloads.php)
 *      - Installez la dll "php_fileinfo.dll" dans le r�pertoire classique d'installation de php
 *      - Ajoutez la ligne suivante dans votre php.ini
 *          [extension=php_fileinfo.dll]
 *      - Assurez-vous que la dll dispose des permissions suffisantes pour �tre ex�cut�e par le serveur web.
 *      - Pour �viter des erreurs � la limite du compr�hensible sous windows, le fichier "magic" est livr� avec la classe upload. Il est issu de l'installation d'Apache 2.0.59.
 */

// D�pr�ci�, gard� pour compatibilit� descendante. Utiliser le bool�en renvoy� par la m�thode Execute() en lieu et place.
global $UploadError;

class Upload {
    
    // constantes m�thode de v�rification des ent�tes 
    const CST_UPL_HEADER_BROWSER  = 0; // Navigateur
    const CST_UPL_HEADER_MIMETYPE = 1; // librairie mime_type
    const CST_UPL_HEADER_FILEINFO = 2; // librairie fileinfo (PECL)
    
    // constantes m�thode d'�criture des fichiers
    const CST_UPL_WRITE_ERASE  = 0;
    const CST_UPL_WRITE_COPY   = 1;
    const CST_UPL_WRITE_IGNORE = 2;
    
    // constantes types d'erreurs 1 : appairage avec les erreurs retourn�es par PHP
    const CST_UPL_ERR_NONE                  = UPLOAD_ERR_OK;            // Aucune erreur, le t�l�chargement est valide
    const CST_UPL_ERR_EXCEED_INI_FILESIZE   = UPLOAD_ERR_INI_SIZE;      // la taille du fichier exc�de la directive max_file_size (php.ini)
    const CST_UPL_ERR_EXCEED_FORM_FILESIZE  = UPLOAD_ERR_FORM_SIZE;     // la taille du fichier exc�de la directive max_file_size (formulaire)
    const CST_UPL_ERR_CORRUPT_FILE          = UPLOAD_ERR_PARTIAL;       // le fichier n'a pas �t� charg� compl�tement
    const CST_UPL_ERR_EMPTY_FILE            = UPLOAD_ERR_NO_FILE;       // champ du formulaire vide
    const CST_UPL_ERR_NO_TMP_DIR            = UPLOAD_ERR_NO_TMP_DIR;    // Un dossier temporaire est manquant. Introduit en PHP 4.3.10 et PHP 5.0.3.
    const CST_UPL_ERR_CANT_WRITE            = UPLOAD_ERR_CANT_WRITE;    // �chec de l'�criture du fichier sur le disque. Introduit en PHP 5.1.0.
    const CST_UPL_ERR_EXTENSION             = UPLOAD_ERR_EXTENSION;     // L'envoi de fichier est arr�t� par l'extension. Introduit en PHP 5.2.0.
    
    // constantes types d'erreurs 2 : erreurs suppl�mentaires d�tect�es par la classe
    const CST_UPL_ERR_UNSAFE_FILE           = 20; // fichier potentiellement dangereux
    const CST_UPL_ERR_WRONG_MIMETYPE        = 21; // le fichier n'est pas conforme � la liste des ent�tes autoris�s
    const CST_UPL_ERR_WRONG_EXTENSION       = 22; // le fichier n'est pas conforme � la liste des extensions autoris�es
    const CST_UPL_ERR_IMG_EXCEED_MAX_WIDTH  = 23; // largeur max de l'image exc�de celle autoris�e
    const CST_UPL_ERR_IMG_EXCEED_MAX_HEIGHT = 24; // hauteur max de l'image exc�de celle autoris�e
    const CST_UPL_ERR_IMG_EXCEED_MIN_WIDTH  = 25; // largeur min de l'image exc�de celle autoris�e
    const CST_UPL_ERR_IMG_EXCEED_MIN_HEIGHT = 26; // hauteur min de l'image exc�de celle autoris�e
    
    const CST_UPL_EXT_FILEINFO  = 'fileinfo';
    const CST_UPL_EXT_MIMEMAGIC = 'mime_magic';
    const CST_UPL_PHP_VERSION   = '5.0.4';
    
    
    /**
     * Etant donn� qu'entre les diff�rents navigateurs les informations sur les ent�tes de fichiers peuvent diff�rer, 
     * il est dor�navant possible de laisser PHP s'occuper du type MIME. L'ajout de cette fonctionnalit� n�cessite 
     * l'activation de la librairie mime_magic ou fileinfo.
     * 
     * Positionn� � self::CST_UPL_HEADER_BROWSER, la v�rification des ent�tes de fichiers se fera comme auparavant, cad via les informations retourn�es par le navigateur.
     * Positionn� � self::CST_UPL_HEADER_MIMETYPE, la v�rification est bas� sur les fonctions Mimetype de php (extension mime_magic)
     * Positionn� � self::CST_UPL_HEADER_FILEINFO, la v�rification est bas� sur la classe fileinfo() (librairie PECL)
     * 
     * @var integer
     */
    public $phpDetectMimeType = self::CST_UPL_HEADER_BROWSER;
    
    
    /**
     * Initialis�e dynamiquement dans la fonction loadPECLInfoLib() suivant le param�trage
     * de la propri�t� $phpDetectMimeType.
     *
     * @var string $path . $filename
     */
    public $magicfile = '';
    
    
    /**
     * Par d�faut la classe g�n�re des champs de formulaire � la norme x-html.
     * 
     * @var boolean
     */
    public $xhtml = true;
    
    
    /**
     * Taille maximale exprim�e en kilo-octets pour l'upload d'un fichier.
     * Valeur par d�faut : celle configur�e dans le php.ini (cf. constructeur).
     * 
     * @var integer
     */
    public $MaxFilesize = null;
    
    
    /**
     * Largeur maximum d'une image exprim�e en pixel.
     * 
     * @var int
     */
    public $ImgMaxWidth = null;
    
    
    /**
     * Hauteur maximum d'une image exprim�e en pixel.
     * 
     * @var int
     */
    public $ImgMaxHeight = null;
    
    
    /**
     * Largeur minimum d'une image exprim�e en pixel.
     * 
     * @var int
     */
    public $ImgMinWidth = null;
    
    
    /**
     * Hauteur minimum d'une image exprim�e en pixel.
     * 
     * @var int
     */
    public $ImgMinHeight = null;
    
    
    /**
     * R�pertoire de destination dans lequel vont �tre charg�s les fichiers.
     * Accepte les chemins relatifs et absolus.
     * 
     * @var string
     */
    public $DirUpload = '';
    
    
    /**
     * Nombre de champs de type file que la classe devra g�rer.
     *
     * @var integer 
     */
    public $Fields = 1;
    
    
    /**
     * Param�tres � ajouter aux champ de type file (ex: balise style, �venements JS...)
     * 
     * @var string
     */
    public $FieldOptions = '';
    
    
    /**
     * D�finit si les champs sont obligatoires ou non.
     * 
     * @var boolean
     */
    public $Required = false;
    
    
    /**
     * Politique de s�curit� max : ignore tous les fichiers ex�cutables / interpr�table.
     * D�pr�ci�. Gard� pour compatibilit� descendante.
     * 
     * @var boolean
     */
    public $SecurityMax = false;
    
    
    /**
     * Permet de pr�ciser un nom pour le fichier � uploader.
     * Peut �tre utilis� conjointement avec les propri�t�s $Suffixe / $Prefixe
     * 
     * @var string
     */
    public $Filename = '';
    
    
    /**
     * Pr�fixe pour le nom du fichier sur le serveur.
     * 
     * @var string
     */
    public $Prefixe = '';
    
    
    /**
     * Suffixe pour le nom du fichier sur le serveur.
     * 
     * @var string
     */
    public $Suffixe = '';
    
    
    /**
     * M�thode � employer pour l'�criture des fichiers si un fichier de m�me nom est pr�sent dans le r�pertoire :
     * self::CST_UPL_WRITE_ERASE  : le fichier du serveur est �cras� par le nouveau fichier.
     * self::CST_UPL_WRITE_COPY   : le nouveau fichier est upload� mais pr�c�d� de la mention 'copie_de_'.
     * self::CST_UPL_WRITE_IGNORE : le nouveau fichier est ignor�.
     * 
     * @var integer
     */
    public $WriteMode = self::CST_UPL_WRITE_ERASE;
    
    
    /**
     * Chaine de caract�res repr�sentant les ent�tes de fichiers autoris�s (mime-type).
     * Les ent�tes doivent �tre s�par�es par des points virgules.
     * <code>$Upload->MimeType = 'image/gif;image/pjpeg';</code>
     * 
     * @var string
     */
    public $MimeType = '';
    
    
    /**
     * Positionn� � [true], une erreur de configuration du composant sera envoy� en sortie �cran et bloquera le script
     * en cours d'ex�cution.
     * 
     * @var boolean
     */
    public $TrackError = true;
    
    
    /** 
     * R�affection des droits utilisateur apr�s �criture du document sur le serveur.
     * 
     * @var string
     */
    public $Permission = 0666;
    
    
    /**
     * Liste des extensions autoris�es s�par�es par un point virgule.
     * <code>$Upload->Extension = ".dat;.csv;.txt";</code>
     * 
     * @var string
     */
    public $Extension = '';
    
    
    /**
     * En remplacement de la variable globale $UploadError.
     *
     * @var boolean.
     */
    private $uplSuccess = true;
    
    
    /**
     * Tableau des erreurs rencontr�s durant l'upload.
     *
     * @var array
     */
    private $ArrOfError = array();
    
    
    /**
     * Propri�t�s temporaires utilis�es lors du parcours de la variable globale $_FILES
     */
    private $_field = 0;                // position du champ dans le formulaire � partir de 1 (0 �tant r�serv� au champ max_file_size)
    private $_size  = 0;                // poids du fichier
    private $_type  = '';               // type mime renvoy� par le navigateur
    private $_name  = '';               // nom du fichier
    private $_temp  = '';               // emplacement temporaire
    private $_ext   = '';               // extension du fichier
    private $_error = UPLOAD_ERR_OK;    // Erreur upload retourn�e par PHP
    
    
    /**
     * Tableaux des messages d'erreurs sur l'�chec d'une upload.
     *
     * @see setError()
     * @var array
     */
    private $errorMsg = array(
        self::CST_UPL_ERR_EXCEED_INI_FILESIZE   => 'Le document [%FILENAME%] exc�de la directive [upload_max_filesize] du fichier de configuration [php.ini].',
        self::CST_UPL_ERR_EXCEED_FORM_FILESIZE  => 'Le document [%FILENAME%] exc�de la directive MAX_FILE_SIZE sp�cifi�e dans le formulaire.',
        self::CST_UPL_ERR_CORRUPT_FILE          => 'Document [%FILENAME%] corrompu.',
        self::CST_UPL_ERR_EMPTY_FILE            => "Le champ [parcourir] du formulaire d'upload n'a pas �t� renseign�.",
        self::CST_UPL_ERR_NO_TMP_DIR            => 'Un dossier temporaire est manquant.',
        self::CST_UPL_ERR_CANT_WRITE            => "�chec de l'�criture du fichier [%FILENAME%] sur le disque.",
        self::CST_UPL_ERR_EXTENSION             => "L'envoi du fichier [%FILENAME%] est arr�t� par l'extension.",
        self::CST_UPL_ERR_UNSAFE_FILE           => 'Document [%FILENAME%] potentiellement dangereux.',
        self::CST_UPL_ERR_WRONG_MIMETYPE        => "Le document [%FILENAME%] n'est pas conforme � la liste des ent�tes autoris�s.",
        self::CST_UPL_ERR_WRONG_EXTENSION       => "Le document [%FILENAME%] n'est pas conforme � la liste des extensions autoris�es.",
        self::CST_UPL_ERR_IMG_EXCEED_MAX_WIDTH  => "La largeur de l'image [%FILENAME%] exc�de celle autoris�e.",
        self::CST_UPL_ERR_IMG_EXCEED_MAX_HEIGHT => "La hauteur de l'image [%FILENAME%] exc�de celle autoris�e.",
        self::CST_UPL_ERR_IMG_EXCEED_MIN_WIDTH  => "La largeur de l'image [%FILENAME%] est inf�rieure � celle autoris�e.",
        self::CST_UPL_ERR_IMG_EXCEED_MIN_HEIGHT => "La hauteur de l'image [%FILENAME%] est inf�rieure � celle autoris�e."
    );
    
    
    
    /**
     * Constructeur.
     */
    public function __construct() {
        $this->MaxFilesize = @preg_replace('M', '', @ini_get('upload_max_filesize')) * 1024;
    }
    
    
    
    /**
     * Lance l'initialisation de la classe pour la g�n�ration du formulaire
     * 
     * @access public
     */
    public function InitForm() {
        $this->SetMaxFilesize();
        $this->CreateFields();
    }
    
    
    
    /**
     * Retourne le tableau des erreurs survenues durant l'upload
     *
     * <code>
     * if (!$Upload->Execute()) {
     *     print_r($Upload-> GetError);
     * }
     * </code>
     *
     * @access public
     * @param integer $num_field num�ro du champ 'file' sur lequel on souhaite r�cup�rer l'erreur
     * @return array
     */
    public function GetError($num_field='') {
        return (Empty($num_field)) ? $this->ArrOfError : $this->ArrOfError[$num_field];
    }
    
    
    
    /**
     * Retourne le tableau contenant les informations sur les fichiers upload�s
     *
     * <code>
     * if (!$Upload->Execute()) {
     *     print_r($Upload->GetSummary());
     * }
     * </code>
     *
     * @access public
     * @param integer $num_field    num�ro du champ 'file' sur lequel on souhaite r�cup�rer les informations
     * @return array                tableau des infos fichiers
     */
    public function GetSummary($num_field = null) {
        
        if (!isSet($num_field)) {
            $result = (isSet($this->Infos)) ? $this->Infos : false;
        }
        else {
            $result = (isSet($this->Infos[$num_field])) ? $this->Infos[$num_field] : false;
        }
        
        return $result;
    }
    
    
    
    /**
     * Lance les diff�rents traitements n�cessaires � l'upload
     * 
     * @return boolean
     */
    public function Execute(){
        @set_time_limit(0);
        
        $this->CheckConfig();
        $this->CheckUpload();
        
        return $this->uplSuccess;
    }
    
    
    
    /**
     * Permet de modifier le message d'erreur en cas d'�chec d'une upload.
     * Le libell� peut contenir le mot cl� %FILENAME%.
     * 
     * @var int    $code_erreur
     * @var string $libelle
     * @see AddError()
     * @return boolean
     */
    public function setErrorMsg($code_erreur, $libelle) {
        
        if (!isSet($this->errorMsg[$code_erreur])) {
            $this->Error('le param�tre $code_erreur pass� � la m�thode [setErrorMsg] est erron�.');
            return false;
        }
        
        $this->errorMsg[$code_erreur] = $libelle;
        
        return true;
    }
    
    
    
    /**
     * M�thode de d�finition des propri�t�s sur les dimensions des images.
     * La v�rification sur le bon format est g�r� dans la m�thode CheckImgPossibility().
     *
     * @param integer $maxWidth
     * @param integer $minWidth
     * @param integer $maxHeight
     * @param integer $minHeight
     */
    public function SetImgDim($maxWidth = null, $minWidth = null, $maxHeight = null, $minHeight = null) {
        $this->ImgMaxHeight = $maxHeight;
        $this->ImgMaxWidth  = $maxWidth;
        $this->ImgMinHeight = $minHeight;
        $this->ImgMinWidth  = $minWidth;
    }
    
    
    
    /**
     * M�thode lan�ant les v�rifications sur les fichiers.
     * Initialisation de la propri�t� $uplSuccess � false si erreur, lance la 
     * m�thode d'�criture toutes les v�rifications sont ok.
     * @access private
     */
    private function CheckUpload() {
        
        if (!isSet($_FILES['userfile']['tmp_name'])) {
            $this->Error('Le tableau contenant les informations des fichiers t�l�charg�s est vide.' . PHP_EOL .
                         'Si vous avez renseign� un champ de fichier, il est probable que la taille de ce dernier exc�de les capacit�s de chargement du serveur.');
        }
        
        $nbFiles = count($_FILES['userfile']['tmp_name']);
        
        // Parcours des fichiers � uploader
        for ($i=0; $i < $nbFiles; $i++)  {
            
            // R�cup des particularit� du fichier dans les propri�t�s temporaires
            $this->_field++;
            $this->_size  = $_FILES['userfile']['size'][$i];
            $this->_type  = $_FILES['userfile']['type'][$i];
            $this->_name  = $_FILES['userfile']['name'][$i];
            $this->_temp  = $_FILES['userfile']['tmp_name'][$i];
            $this->_ext   = strtolower(substr($this->_name, strrpos($this->_name, '.')));
            $this->_error = $_FILES['userfile']['error'][$i];
            
            // On ex�cute les v�rifications demand�es
            if ($this->_error == UPLOAD_ERR_OK && is_uploaded_file($_FILES['userfile']['tmp_name'][$i])) {
                
                // V�rification du type mime via la librairie "mime_magic" : on surcharge la propri�t� _type avec le type renvoy� par la fonction mime_content_type
                if ($this->phpDetectMimeType === self::CST_UPL_HEADER_MIMETYPE) {
                    $this->_type = mime_content_type($_FILES['userfile']['tmp_name'][$i]);
                }
                
                // V�rification du type mime via la librairie "file_info" : on surcharge la propri�t� _type avec le type renvoy� par la fonction fileinfo()
                else if ($this->phpDetectMimeType === self::CST_UPL_HEADER_FILEINFO) {
                    
                    $fInfo = new finfo(FILEINFO_MIME, $this->magicfile);
                    
                    // La classe retourne une chaine de type "mime; charset". Seul la partie mime nous int�resse.
                    $mime = explode(';', $fInfo->file($_FILES['userfile']['tmp_name'][$i]));
                    
                    $this->_type = trim($mime[0]);
                    
                    unset($fInfo, $mime);
                }
                
                // V�rification des erreurs supl�mentaires d�tect�es par la classe
                if (!$this->CheckSecurity() || !$this->CheckMimeType() || !$this->CheckExtension() || !$this->CheckImg()) {
                    continue;
                }                
            }
            else {
                // Erreur retourn�e par PHP
                $this->AddError($this->_error);
                continue;
            }
            
            // Le fichier a pass� toutes les v�rifications, on proc�de � l'�criture
            $this->WriteFile($this->_name, $this->_type, $this->_temp, $this->_ext, $this->_field);
        }
    }
    
    
    
    /**
     * Ecrit le fichier sur le serveur.
     *
     * @access private
     * @param string $name        nom du fichier sans son extension
     * @param string $type        entete du fichier
     * @param string $temp        chemin du fichier temporaire
     * @param string $temp        extension du fichier pr�c�d�e d'un point
     * @param string $num_fied    position du champ dans le formulaire � compter de 1
     * @return bool               true/false => succes/erreur
     */
    private function WriteFile($name, $type, $temp, $ext, $num_field) {
        
        $new_filename = null;
        
        if (is_uploaded_file($temp)) {
            
            // Nettoyage du nom original du fichier
            $new_filename = (Empty($this->Filename)) ? $this->CleanFileName(substr($name, 0, strrpos($name, '.'))) : $this->Filename;
            
            // Ajout pr�fixes / suffixes + extension :
            $new_filename = $this->Prefixe . $new_filename . $this->Suffixe . $ext;
            
            switch ($this->WriteMode) {
                
                case self::CST_UPL_WRITE_ERASE :
                    $uploaded = @move_uploaded_file($temp, $this->DirUpload . $new_filename);
                break;
                    
                case self::CST_UPL_WRITE_COPY :
                    
                    if ($this->AlreadyExist($new_filename)) {
                        $new_filename = 'copie_de_' . $new_filename;
                    }
                    
                    $uploaded = @move_uploaded_file($temp, $this->DirUpload . $new_filename);
                    
                 break;
                
                case self::CST_UPL_WRITE_IGNORE : 
                
                    if ($this->AlreadyExist($new_filename)) $uploaded = true;
                    else                                    $uploaded = @move_uploaded_file($temp, $this->DirUpload . $new_filename);
                    
                break;
            }
            
            // Informations pouvant �tre utiles au d�veloppeur (si le fichier a pu �tre copi�)
            if ($uploaded) {
                
                $filesize = filesize($this->DirUpload . $new_filename);
                
                $this->Infos[$num_field]['nom']          = $new_filename;
                $this->Infos[$num_field]['nom_originel'] = $name;
                $this->Infos[$num_field]['chemin']       = $this->DirUpload . $new_filename;
                $this->Infos[$num_field]['poids']        = number_format($filesize/1024, 3, '.', '');
                $this->Infos[$num_field]['octets']       = $filesize;
                $this->Infos[$num_field]['mime-type']    = $type;
                $this->Infos[$num_field]['extension']    = $ext;
            }
            else {
                $this->Error('move_uploaded_file() a g�n�r� une erreur. V�rifiez les droits d\'�criture du r�pertoire temporaire d\'upload [' . @ini_get('upload_tmp_dir') . '] et celui du r�pertoire de destination [' . $this->DirUpload . '].');
                return false;
            }
            
            // Mise en place des droits
            if (function_exists('chmod')) {
                @chmod($this->DirUpload . $new_filename, $this->Permission);
            }
            
            return true;
            
        } // End is_uploaded_file
        
        return false;
    }
    
    
    
    /**
     * V�rifie si le fichier pass� en param�tre existe d�j� dans le r�pertoire DirUpload
     * 
     * @access private
     * @return bool
     */
    private function AlreadyExist($file) {
        return (file_exists($this->DirUpload . $file));
    }
    
    
    
    /**
     * V�rifie la hauteur/largeur d'une image
     * 
     * @access private
     * @return bool
     */
    private function CheckImg() {
        
        $dim = @getimagesize($this->_temp);
        $res = true;
        
        // On travaille sur un fichier image
        if ($dim != false) {
            
            if (!Empty($this->ImgMaxWidth) && $dim[0] > $this->ImgMaxWidth)  {
                $this->AddError(self::CST_UPL_ERR_IMG_EXCEED_MAX_WIDTH);
                $res = false;
            }
            
            if (!Empty($this->ImgMaxHeight) && $dim[1] > $this->ImgMaxHeight) {
                $this->AddError(self::CST_UPL_ERR_IMG_EXCEED_MAX_HEIGHT);
                $res = false;
            }
            
            if (!Empty($this->ImgMinWidth)  && $dim[0] < $this->ImgMinWidth) {
                $this->AddError(self::CST_UPL_ERR_IMG_EXCEED_MIN_WIDTH);
                $res = false;
            }
            
            if (!Empty($this->ImgMinHeight) && $dim[1] < $this->ImgMinHeight) {
                $this->AddError(self::CST_UPL_ERR_IMG_EXCEED_MIN_HEIGHT);
                $res = false;
            }
        }
                
        return $res;
    }
    
    
    
    /**
     * V�rifie l'extension des fichiers suivant celles pr�cis�es dans $Extension
     * @access private
     * @return bool
     */
    private function CheckExtension() {
        
        $ArrOfExtension = explode(';', strtolower($this->Extension));
        
        if (!Empty($this->Extension) && !in_array($this->_ext, $ArrOfExtension)) {
            $this->AddError(self::CST_UPL_ERR_WRONG_EXTENSION);
            return false;
        }
        
        return true;
    }
    
    
    
    /**
     * V�rifie l'ent�te des fichiers suivant ceux pr�cis�s dans $MimeType
     * @access private
     * @return bool
     */
    private function CheckMimeType() {
        
        $ArrOfMimeType = explode(';', $this->MimeType);
        
        if (!Empty($this->MimeType) && !in_array($this->_type, $ArrOfMimeType)) {
            $this->AddError(self::CST_UPL_ERR_WRONG_MIMETYPE);
            return false;
        }
        
        return true;
    }
    
    
    /**
     * Ajoute une erreur pour le fichier en cours de lecture dans le tableau des erreur.
     * Voir http://www.php.net/manual/fr/features.file-upload.errors.php
     * 
     * @access private
     */
    private function AddError($code_erreur) {
        
        // D�pr�ci�. Gard� pour compatibilit�.
        global $UploadError;
        
        $positionnerEnErreur = true;
        
        switch ($code_erreur) {
            
            case self::CST_UPL_ERR_NONE :
               $positionnerEnErreur = false;
            break;
            
            case '' :
                $msg = 'Exception lev�e mais non d�cel�e pour le document %FILENAME%.';
            break;
            
            case self::CST_UPL_ERR_EMPTY_FILE :
                $msg = $this->errorMsg[$code_erreur];
                $positionnerEnErreur = $this->Required;
            break;
            
            default :
                $msg = $this->errorMsg[$code_erreur];
                $positionnerEnErreur = true;
            break;
            
        }
        
        if ($positionnerEnErreur) {
            
            $msg              = str_replace('%FILENAME%', utf8_decode($this->_name), $msg);
            $UploadError      = true;
            $this->uplSuccess = false;
            
            $this->ArrOfError[$this->_field][$code_erreur] = $msg;
        }
    }
    
    
    
    /**
     * V�rifie les crit�res de la politique de s�curit�
     * OV : 26/10/07 => d�pr�ci�.
     * 
     * @access private
     * @return bool
     */
    private function CheckSecurity() {
        
        // Bloque tous les fichiers executables, et tous les fichiers php pouvant �tre interpr�t� mais dont l'ent�te ne peut les identifier comme �tant dangereux
        if ($this->SecurityMax === true && ereg ('application/octet-stream', $this->_type) || preg_match("/.php$|.inc$|.php3$/i", $this->_ext)) {
            $this->AddError(self::CST_UPL_ERR_UNSAFE_FILE);
            return false;
        }
        
        return true;
    }
    
    
    
    /**
     * V�rifie et formate le chemin de destination :
     *     - d�finit comme rep par d�faut celui de la classe
     *     - teste l'existance du r�pertoire et son acc�s en �criture
     * @access private
     */
    private function CheckDirUpload() {
        
        // Si aucun r�pertoire n'a �t� pr�cis�, on prend celui de la classe
        if (Empty($this->DirUpload)) $this->DirUpload = dirname(__FILE__);
        
        $this->DirUpload = $this->FormatDir($this->DirUpload);
        
        // Le r�pertoire existe?
        if (!is_dir($this->DirUpload)) $this->Error('Le r�pertoire de destination sp�cifi�e par la propri�t� DirUpload n\'existe pas.');
        
        // Anciennement, le test sur le droit en �criture �tait g�r� via la fonction is_writeable() ici.
        // Malheureusement, pour des raisons inconnus, ce test pouvait g�n�r� une erreur alors que le r�pertoire de destination �tait correctement configur� (Windows Server 2003).
        // Le test est finalement d�localis� lors de l'�criture du fichier via la fonction move_uploaded_file().
    }
    
    
    
    /**
     * Formate le r�pertoire pass� en param�tre
     * - convertit un chemin relatif en chemin absolu
     * - ajoute si besoin le dernier slash (ou antislash suivant le syst�me)
     * 
     * @access private
     */
    private function FormatDir($Dir) {
        
        // Convertit les chemins relatifs en chemins absolus
        if (function_exists('realpath')) {
            if (realpath($Dir)) $Dir = realpath($Dir);
        }
        
        // Position du dernier slash/antislash
        if ($Dir[strlen($Dir)-1] != DIRECTORY_SEPARATOR) $Dir .= DIRECTORY_SEPARATOR;
        
        return $Dir;
    }
    
    
    
    /**
     * Formate la chaine pass�e en param�tre en nom de fichier standard (pas de caract�res sp�ciaux ni d'espaces)
     * @access private
     * @param  string $str   chaine � formater
     * @return string        chaine format�e
     */
    private function CleanFileName($str) {
        
        $return = '';
        
        for ($i=0; $i <= strlen($str)-1; $i++) {
            if (eregi('[a-z]',$str{$i}))              $return .= $str{$i};
            elseif (eregi('[0-9]', $str{$i}))         $return .= $str{$i};
            elseif (ereg('[������������]', $str{$i})) $return .= 'a';
            elseif (ereg('[��]', $str{$i}))           $return .= 'a';
            elseif (ereg('[��]', $str{$i}))           $return .= 'c';
            elseif (ereg('[��������E]', $str{$i}))    $return .= 'e';
            elseif (ereg('[��������]', $str{$i}))     $return .= 'i';
            elseif (ereg('[����������]', $str{$i}))   $return .= 'o';
            elseif (ereg('[��������]', $str{$i}))     $return .= 'u';
            elseif (ereg('[��ݟ]', $str{$i}))         $return .= 'y';
            elseif (ereg('[ ]', $str{$i}))            $return .= '_';
            elseif (ereg('[.]', $str{$i}))            $return .= '_';
            else                                      $return .= $str{$i};
        }
        
        return utf8_encode(str_replace(array('\\', '/', ':', '*', '?', '"', '<', '>', '|'), '', $return));
    }
    
    
    
    /**
     * Conversion du poids maximum d'un fichier exprim�e en Ko en octets
     * @access private
     */
    private function SetMaxFilesize() {
        (is_numeric($this->MaxFilesize)) ? $this->MaxFilesize = $this->MaxFilesize * 1024 : $this->Error('la propri�t� MaxFilesize doit �tre une valeur num�rique');
    }
    
    
    
    /**
     * Cr�e les champs de type fichier suivant la propri�t� Fields dans un tableau $Field. Ajoute le contenu de FieldOptions aux champs.
     * @access private
     */
    private function CreateFields() {
        
        if (!is_int($this->Fields)) {
            $this->Error('la propri�t� Fields doit �tre un entier');
        }
        
        for ($i=0; $i <= $this->Fields; $i++) {
            if ($i == 0)  $this->Field[] = ($this->xhtml) ? '<input type="hidden" name="MAX_FILE_SIZE" value="'. $this->MaxFilesize .'" />' : '<input type="hidden" name="MAX_FILE_SIZE" value="'. $this->MaxFilesize .'">';
            else          $this->Field[] = ($this->xhtml) ? '<input type="file" name="userfile[]" '. $this->FieldOptions .'/>'              : '<input type="file" name="userfile[]" '. $this->FieldOptions .'>';
        }
    }
    
    
    
    /**
     * V�rifie la configuration de la classe.
     * @access private
     */
    private function CheckConfig() {
        
        if (!version_compare(phpversion(), self::CST_UPL_PHP_VERSION)) {
            $this->Error('Version PHP minimale requise : ' . self::CST_UPL_PHP_VERSION . '.');
        }
        
        if (ini_get('file_uploads') != 1) {
            $this->Error('la configuration du serveur ne vous autorise pas � faire du transfert de fichier. V�rifiez la propri�t� [file_uploads] du fichier [php.ini].');
        }
        
        if (!is_string($this->Extension)) $this->Error('la propri�t� Extension est mal configur�e.');
        if (!is_string($this->MimeType))  $this->Error('la propri�t� MimeType est mal configur�e.');
        if (!is_string($this->Filename))  $this->Error('la propri�t� Filename est mal configur�e.');
        if (!is_bool($this->Required))    $this->Error('la propri�t� Required est mal configur�e.');
        if (!is_bool($this->SecurityMax)) $this->Error('la propri�t� SecurityMax est mal configur�e.');
        
        if ($this->WriteMode != self::CST_UPL_WRITE_COPY && $this->WriteMode != self::CST_UPL_WRITE_ERASE && $this->WriteMode != self::CST_UPL_WRITE_IGNORE) {
            $this->Error('la propri�t� WriteMode est mal configur�e.');
        }
                
        $this->CheckImgPossibility();
        $this->CheckDirUpload();
        
        // V�rification de la propri�t� $phpDetectMimeType.
        if (!is_int($this->phpDetectMimeType) || ($this->phpDetectMimeType != self::CST_UPL_HEADER_BROWSER && $this->phpDetectMimeType != self::CST_UPL_HEADER_FILEINFO && $this->phpDetectMimeType != self::CST_UPL_HEADER_MIMETYPE)) {
            $this->Error('la propri�t� phpDetectMimeType est mal configur�e.');       
        }
        else if ($this->phpDetectMimeType === self::CST_UPL_HEADER_MIMETYPE) {
            $this->loadMimeTypeLib();
        }
        else if ($this->phpDetectMimeType === self::CST_UPL_HEADER_FILEINFO) {
            $this->loadPECLInfoLib();
        }
    }
    
    
    
    /**
     * V�rifie les propri�t�s ImgMaxWidth/ImgMaxHeight
     * @access private
     */
    private function CheckImgPossibility() {
        if (!Empty($this->ImgMaxWidth)  && !is_numeric($this->ImgMaxWidth))  $this->Error('la propri�t� ImgMaxWidth est mal configur�e.');
        if (!Empty($this->ImgMaxHeight) && !is_numeric($this->ImgMaxHeight)) $this->Error('la propri�t� ImgMaxHeight est mal configur�e.');
        if (!Empty($this->ImgMinWidth)  && !is_numeric($this->ImgMinWidth))  $this->Error('la propri�t� ImgMinWidth est mal configur�e.');
        if (!Empty($this->ImgMinHeight) && !is_numeric($this->ImgMinHeight)) $this->Error('la propri�t� ImgMinHeight est mal configur�e.');
    }
    
    
    
    /** 
     * Essaie de charger la librairie MimeType.
     * 
     * @access  private
     * @return  bool
     */
    private function loadMimeTypeLib() {
        
        if(!extension_loaded(self::CST_UPL_EXT_MIMEMAGIC)) @dl(self::CST_UPL_EXT_MIMEMAGIC . PHP_SHLIB_SUFFIX);
        
        if(!extension_loaded(self::CST_UPL_EXT_MIMEMAGIC)) {
            trigger_error('Impossible de charger la librairie ' . self::CST_UPL_EXT_MIMEMAGIC . '(http://fr3.php.net/manual/fr/ref.mime-magic.php). La v�rification des ent�tes de fichiers se fera par le biais des informations retourn�es par la navigateur.', E_USER_WARNING);
            $this->phpDetectMimeType = self::CST_UPL_HEADER_BROWSER;
            return false;
        }
        
        return true;
    }
    
    
    
    /** 
     * Essaie de charger la librairie PECL.
     * Note : impossible d'activer � la vol�e cette extension.
     * 
     * @access  private
     * @return  bool
     */
    private function loadPECLInfoLib() {
        
        if(!extension_loaded(self::CST_UPL_EXT_FILEINFO)) {
            trigger_error('Impossible de charger la librairie ' . self::CST_UPL_EXT_FILEINFO . ' (http://fr3.php.net/manual/fr/ref.fileinfo). La v�rification des ent�tes de fichiers se fera par le biais des informations retourn�es par la navigateur.', E_USER_WARNING);
            $this->phpDetectMimeType = self::CST_UPL_HEADER_BROWSER;
            return false;
        }
        
        $this->magicfile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mime_magic' . DIRECTORY_SEPARATOR . 'magic';
        
        if (!is_file($this->magicfile)) {
            trigger_error('Impossible de charger le fichier "magic" n�c�ssaire � la librairie FileInfo. La v�rification des ent�tes de fichiers se fera par le biais des informations retourn�es par la navigateur.', E_USER_WARNING);
            $this->phpDetectMimeType = self::CST_UPL_HEADER_BROWSER;
            return false;
        }
        
        return true;
    }
    
    
    
    /**
     * Affiche les erreurs de configuration et stoppe tout traitement 
     * 
     * @var string $error_msg
     */
    private function Error($error_msg) {
        
        if ($this->TrackError) {
            trigger_error('Erreur [' . get_class($this) . '] : ' . $error_msg, E_USER_ERROR);
            exit;
        }
    }
    
} // End Class
?>