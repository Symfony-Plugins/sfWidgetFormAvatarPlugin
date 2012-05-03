<?php ini_set('display_errors','off');
/**
 * @version   2.2a, dernière révision le 17 mars 2008
 * @author    Olivier VEUJOZ
 * 
 * Modifications version 2.2a
 *   - Correction du paramètrage par défaut de la propriété $Permission (string => integer)
 * 
 * Modifications version 2.2 :
 *   - Ajout des derniers messages d'erreurs retournés par PHP (UPLOAD_ERR_EXTENSION, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_NO_TMP_DIR)
 *   - Ajout de la propriété 'octets' dans le tableau des informations sur un fichier (taille du fichier en octets)
 *   - Ajout du test sur la présence du tableau $_FILES
 *   - Modifications mineures sur la logique de la classe (fonction checkUpload() et sur la fonction de nettoyage du nom de fichier, qui supprime tout caractère de fichier Windows invalide.
 * 
 * SECURITY CONSIDERATION: If you are saving all uploaded files to a directory accesible with an URL, remember to filter files not only by mime-type (e.g. image/gif), but also by extension. The mime-type is reported by the client, if you trust him, he can upload a php file as an image and then request it, executing malicious code. 
 * I hope I am not giving hackers a good idea anymore than I am giving it to good-intended developers. Cheers.
 * Some restrictive firewalls may not let file uploads happen via a form with enctype="multipart/form-data".
 * We were having problems with an upload script hanging (not returning content) when a file was uploaded through a remote office firewall. Removing the enctype parameter of the form allowed the form submit to happen but then broke the file upload capability. Everything but the file came through. Using a dial-in or other Internet connection (bypassing the bad firewall) allowed everything to function correctly.
 * So if your upload script does not respond when uploading a file, it may be a firewall issue.
 * 
 * Compatibilité :
 *  - compatible safe_mode
 *  - compatible open_basedir pour peu que les droits sur le répertoire temporaire d'upload soient alloués
 *  - Version minimum de php : 5.x
 * 
 * Par défaut :
 *  - autorise tout type de fichier
 *  - autorise les fichier allant jusqu'à la taille maximale spécifiée dans le php.ini
 *  - envoie le(s) fichier(s) dans le répertoire de la classe
 *  - n'affiche qu'un champ de type file
 *  - permet de laisser les champs de fichiers vides
 *  - écrase le fichier s'il existe déjà
 *  - n'exécute aucune vérification
 *  - utilise les entêtes renvoyés par le navigateur pour vérifier le type mime.
 * 
 * Notes :
 *  - le chemin de destination peut être défini en absolu ou en relatif
 *  - set_time_limit n'a pas d'effet lorsque PHP fonctionne en mode safe mode . Il n'y a pas d'autre solution que de changer de mode, ou de modifier la durée maximale d'exécution dans le php.ini
 *  - Intégration depuis la version 2.0b des fonctions Mimetype de php (http://fr3.php.net/manual/fr/ref.mime-magic.php).
 * 
 * Notes sur l'intégration des fonctions MimeType de PHP:
 *      - PHP doit être compilé avec l'option --enable-mime-magic. Sous Windows, il suffit de s'assurer de l'existence de la dll php_mime_magic.dll et de l'activer dans le php.ini
 *      - Déclarer ensuite une nouvelle section dans votre php.ini et renseignez là comme suit :
 *          [MIME_MAGIC]
 *          ;PHP_INI_SYSTEM Disponible depuis PHP 5.0.0. 
 *          mime_magic.debug = 0
 *          ;PHP_INI_SYSTEM Disponible depuis PHP 4.3.0. 
 *          mime_magic.magicfile = "$PHP_INSTALL_DIR\magic.mime" où $PHP_INSTALL_DIR fait référence à  votre chemin jusqu'à l'exécutable PHP
 *      - Le fichier magic.mime n'est pas fourni avec PHP. Il est téléchargeable http://gnuwin32.sourceforge.net/packages/file.htm (dans l'arborescence \share\file\)
 *        Il est recommandé de le copier à la racine de l'exécutable PHP. (étape nécessaire sous windows, pas sûr pour les autres OS)
 * 
 * Notes sur l'installation de la librairie PECL plateforme windows
 *      - Télécharger la collection de modules PECL depuis la page de téléchargement général de PHP en adéquation avec la version de PHP utilisée ("Collection of PECL modules", http://www.php.net/downloads.php)
 *      - Installez la dll "php_fileinfo.dll" dans le répertoire classique d'installation de php
 *      - Ajoutez la ligne suivante dans votre php.ini
 *          [extension=php_fileinfo.dll]
 *      - Assurez-vous que la dll dispose des permissions suffisantes pour être exécutée par le serveur web.
 *      - Pour éviter des erreurs à la limite du compréhensible sous windows, le fichier "magic" est livré avec la classe upload. Il est issu de l'installation d'Apache 2.0.59.
 */

// Déprécié, gardé pour compatibilité descendante. Utiliser le booléen renvoyé par la méthode Execute() en lieu et place.
global $UploadError;

class Upload {
    
    // constantes méthode de vérification des entêtes 
    const CST_UPL_HEADER_BROWSER  = 0; // Navigateur
    const CST_UPL_HEADER_MIMETYPE = 1; // librairie mime_type
    const CST_UPL_HEADER_FILEINFO = 2; // librairie fileinfo (PECL)
    
    // constantes méthode d'écriture des fichiers
    const CST_UPL_WRITE_ERASE  = 0;
    const CST_UPL_WRITE_COPY   = 1;
    const CST_UPL_WRITE_IGNORE = 2;
    
    // constantes types d'erreurs 1 : appairage avec les erreurs retournées par PHP
    const CST_UPL_ERR_NONE                  = UPLOAD_ERR_OK;            // Aucune erreur, le téléchargement est valide
    const CST_UPL_ERR_EXCEED_INI_FILESIZE   = UPLOAD_ERR_INI_SIZE;      // la taille du fichier excède la directive max_file_size (php.ini)
    const CST_UPL_ERR_EXCEED_FORM_FILESIZE  = UPLOAD_ERR_FORM_SIZE;     // la taille du fichier excède la directive max_file_size (formulaire)
    const CST_UPL_ERR_CORRUPT_FILE          = UPLOAD_ERR_PARTIAL;       // le fichier n'a pas été chargé complètement
    const CST_UPL_ERR_EMPTY_FILE            = UPLOAD_ERR_NO_FILE;       // champ du formulaire vide
    const CST_UPL_ERR_NO_TMP_DIR            = UPLOAD_ERR_NO_TMP_DIR;    // Un dossier temporaire est manquant. Introduit en PHP 4.3.10 et PHP 5.0.3.
    const CST_UPL_ERR_CANT_WRITE            = UPLOAD_ERR_CANT_WRITE;    // Échec de l'écriture du fichier sur le disque. Introduit en PHP 5.1.0.
    const CST_UPL_ERR_EXTENSION             = UPLOAD_ERR_EXTENSION;     // L'envoi de fichier est arrêté par l'extension. Introduit en PHP 5.2.0.
    
    // constantes types d'erreurs 2 : erreurs supplémentaires détectées par la classe
    const CST_UPL_ERR_UNSAFE_FILE           = 20; // fichier potentiellement dangereux
    const CST_UPL_ERR_WRONG_MIMETYPE        = 21; // le fichier n'est pas conforme à la liste des entêtes autorisés
    const CST_UPL_ERR_WRONG_EXTENSION       = 22; // le fichier n'est pas conforme à la liste des extensions autorisées
    const CST_UPL_ERR_IMG_EXCEED_MAX_WIDTH  = 23; // largeur max de l'image excède celle autorisée
    const CST_UPL_ERR_IMG_EXCEED_MAX_HEIGHT = 24; // hauteur max de l'image excède celle autorisée
    const CST_UPL_ERR_IMG_EXCEED_MIN_WIDTH  = 25; // largeur min de l'image excède celle autorisée
    const CST_UPL_ERR_IMG_EXCEED_MIN_HEIGHT = 26; // hauteur min de l'image excède celle autorisée
    
    const CST_UPL_EXT_FILEINFO  = 'fileinfo';
    const CST_UPL_EXT_MIMEMAGIC = 'mime_magic';
    const CST_UPL_PHP_VERSION   = '5.0.4';
    
    
    /**
     * Etant donné qu'entre les différents navigateurs les informations sur les entêtes de fichiers peuvent différer, 
     * il est dorénavant possible de laisser PHP s'occuper du type MIME. L'ajout de cette fonctionnalité nécessite 
     * l'activation de la librairie mime_magic ou fileinfo.
     * 
     * Positionné à self::CST_UPL_HEADER_BROWSER, la vérification des entêtes de fichiers se fera comme auparavant, cad via les informations retournées par le navigateur.
     * Positionné à self::CST_UPL_HEADER_MIMETYPE, la vérification est basé sur les fonctions Mimetype de php (extension mime_magic)
     * Positionné à self::CST_UPL_HEADER_FILEINFO, la vérification est basé sur la classe fileinfo() (librairie PECL)
     * 
     * @var integer
     */
    public $phpDetectMimeType = self::CST_UPL_HEADER_BROWSER;
    
    
    /**
     * Initialisée dynamiquement dans la fonction loadPECLInfoLib() suivant le paramétrage
     * de la propriété $phpDetectMimeType.
     *
     * @var string $path . $filename
     */
    public $magicfile = '';
    
    
    /**
     * Par défaut la classe génère des champs de formulaire à la norme x-html.
     * 
     * @var boolean
     */
    public $xhtml = true;
    
    
    /**
     * Taille maximale exprimée en kilo-octets pour l'upload d'un fichier.
     * Valeur par défaut : celle configurée dans le php.ini (cf. constructeur).
     * 
     * @var integer
     */
    public $MaxFilesize = null;
    
    
    /**
     * Largeur maximum d'une image exprimée en pixel.
     * 
     * @var int
     */
    public $ImgMaxWidth = null;
    
    
    /**
     * Hauteur maximum d'une image exprimée en pixel.
     * 
     * @var int
     */
    public $ImgMaxHeight = null;
    
    
    /**
     * Largeur minimum d'une image exprimée en pixel.
     * 
     * @var int
     */
    public $ImgMinWidth = null;
    
    
    /**
     * Hauteur minimum d'une image exprimée en pixel.
     * 
     * @var int
     */
    public $ImgMinHeight = null;
    
    
    /**
     * Répertoire de destination dans lequel vont être chargés les fichiers.
     * Accepte les chemins relatifs et absolus.
     * 
     * @var string
     */
    public $DirUpload = '';
    
    
    /**
     * Nombre de champs de type file que la classe devra gérer.
     *
     * @var integer 
     */
    public $Fields = 1;
    
    
    /**
     * Paramètres à ajouter aux champ de type file (ex: balise style, évenements JS...)
     * 
     * @var string
     */
    public $FieldOptions = '';
    
    
    /**
     * Définit si les champs sont obligatoires ou non.
     * 
     * @var boolean
     */
    public $Required = false;
    
    
    /**
     * Politique de sécurité max : ignore tous les fichiers exécutables / interprétable.
     * Déprécié. Gardé pour compatibilité descendante.
     * 
     * @var boolean
     */
    public $SecurityMax = false;
    
    
    /**
     * Permet de préciser un nom pour le fichier à uploader.
     * Peut être utilisé conjointement avec les propriétés $Suffixe / $Prefixe
     * 
     * @var string
     */
    public $Filename = '';
    
    
    /**
     * Préfixe pour le nom du fichier sur le serveur.
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
     * Méthode à employer pour l'écriture des fichiers si un fichier de même nom est présent dans le répertoire :
     * self::CST_UPL_WRITE_ERASE  : le fichier du serveur est écrasé par le nouveau fichier.
     * self::CST_UPL_WRITE_COPY   : le nouveau fichier est uploadé mais précédé de la mention 'copie_de_'.
     * self::CST_UPL_WRITE_IGNORE : le nouveau fichier est ignoré.
     * 
     * @var integer
     */
    public $WriteMode = self::CST_UPL_WRITE_ERASE;
    
    
    /**
     * Chaine de caractères représentant les entêtes de fichiers autorisés (mime-type).
     * Les entêtes doivent être séparées par des points virgules.
     * <code>$Upload->MimeType = 'image/gif;image/pjpeg';</code>
     * 
     * @var string
     */
    public $MimeType = '';
    
    
    /**
     * Positionné à [true], une erreur de configuration du composant sera envoyé en sortie écran et bloquera le script
     * en cours d'exécution.
     * 
     * @var boolean
     */
    public $TrackError = true;
    
    
    /** 
     * Réaffection des droits utilisateur après écriture du document sur le serveur.
     * 
     * @var string
     */
    public $Permission = 0666;
    
    
    /**
     * Liste des extensions autorisées séparées par un point virgule.
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
     * Tableau des erreurs rencontrés durant l'upload.
     *
     * @var array
     */
    private $ArrOfError = array();
    
    
    /**
     * Propriétés temporaires utilisées lors du parcours de la variable globale $_FILES
     */
    private $_field = 0;                // position du champ dans le formulaire à partir de 1 (0 étant réservé au champ max_file_size)
    private $_size  = 0;                // poids du fichier
    private $_type  = '';               // type mime renvoyé par le navigateur
    private $_name  = '';               // nom du fichier
    private $_temp  = '';               // emplacement temporaire
    private $_ext   = '';               // extension du fichier
    private $_error = UPLOAD_ERR_OK;    // Erreur upload retournée par PHP
    
    
    /**
     * Tableaux des messages d'erreurs sur l'échec d'une upload.
     *
     * @see setError()
     * @var array
     */
    private $errorMsg = array(
        self::CST_UPL_ERR_EXCEED_INI_FILESIZE   => 'Le document [%FILENAME%] excède la directive [upload_max_filesize] du fichier de configuration [php.ini].',
        self::CST_UPL_ERR_EXCEED_FORM_FILESIZE  => 'Le document [%FILENAME%] excède la directive MAX_FILE_SIZE spécifiée dans le formulaire.',
        self::CST_UPL_ERR_CORRUPT_FILE          => 'Document [%FILENAME%] corrompu.',
        self::CST_UPL_ERR_EMPTY_FILE            => "Le champ [parcourir] du formulaire d'upload n'a pas été renseigné.",
        self::CST_UPL_ERR_NO_TMP_DIR            => 'Un dossier temporaire est manquant.',
        self::CST_UPL_ERR_CANT_WRITE            => "Échec de l'écriture du fichier [%FILENAME%] sur le disque.",
        self::CST_UPL_ERR_EXTENSION             => "L'envoi du fichier [%FILENAME%] est arrêté par l'extension.",
        self::CST_UPL_ERR_UNSAFE_FILE           => 'Document [%FILENAME%] potentiellement dangereux.',
        self::CST_UPL_ERR_WRONG_MIMETYPE        => "Le document [%FILENAME%] n'est pas conforme à la liste des entêtes autorisés.",
        self::CST_UPL_ERR_WRONG_EXTENSION       => "Le document [%FILENAME%] n'est pas conforme à la liste des extensions autorisées.",
        self::CST_UPL_ERR_IMG_EXCEED_MAX_WIDTH  => "La largeur de l'image [%FILENAME%] excède celle autorisée.",
        self::CST_UPL_ERR_IMG_EXCEED_MAX_HEIGHT => "La hauteur de l'image [%FILENAME%] excède celle autorisée.",
        self::CST_UPL_ERR_IMG_EXCEED_MIN_WIDTH  => "La largeur de l'image [%FILENAME%] est inférieure à celle autorisée.",
        self::CST_UPL_ERR_IMG_EXCEED_MIN_HEIGHT => "La hauteur de l'image [%FILENAME%] est inférieure à celle autorisée."
    );
    
    
    
    /**
     * Constructeur.
     */
    public function __construct() {
        $this->MaxFilesize = @preg_replace('M', '', @ini_get('upload_max_filesize')) * 1024;
    }
    
    
    
    /**
     * Lance l'initialisation de la classe pour la génération du formulaire
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
     * @param integer $num_field numéro du champ 'file' sur lequel on souhaite récupérer l'erreur
     * @return array
     */
    public function GetError($num_field='') {
        return (Empty($num_field)) ? $this->ArrOfError : $this->ArrOfError[$num_field];
    }
    
    
    
    /**
     * Retourne le tableau contenant les informations sur les fichiers uploadés
     *
     * <code>
     * if (!$Upload->Execute()) {
     *     print_r($Upload->GetSummary());
     * }
     * </code>
     *
     * @access public
     * @param integer $num_field    numéro du champ 'file' sur lequel on souhaite récupérer les informations
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
     * Lance les différents traitements nécessaires à l'upload
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
     * Permet de modifier le message d'erreur en cas d'échec d'une upload.
     * Le libellé peut contenir le mot clé %FILENAME%.
     * 
     * @var int    $code_erreur
     * @var string $libelle
     * @see AddError()
     * @return boolean
     */
    public function setErrorMsg($code_erreur, $libelle) {
        
        if (!isSet($this->errorMsg[$code_erreur])) {
            $this->Error('le paramètre $code_erreur passé à la méthode [setErrorMsg] est erroné.');
            return false;
        }
        
        $this->errorMsg[$code_erreur] = $libelle;
        
        return true;
    }
    
    
    
    /**
     * Méthode de définition des propriétés sur les dimensions des images.
     * La vérification sur le bon format est géré dans la méthode CheckImgPossibility().
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
     * Méthode lançant les vérifications sur les fichiers.
     * Initialisation de la propriété $uplSuccess à false si erreur, lance la 
     * méthode d'écriture toutes les vérifications sont ok.
     * @access private
     */
    private function CheckUpload() {
        
        if (!isSet($_FILES['userfile']['tmp_name'])) {
            $this->Error('Le tableau contenant les informations des fichiers téléchargés est vide.' . PHP_EOL .
                         'Si vous avez renseigné un champ de fichier, il est probable que la taille de ce dernier excède les capacités de chargement du serveur.');
        }
        
        $nbFiles = count($_FILES['userfile']['tmp_name']);
        
        // Parcours des fichiers à uploader
        for ($i=0; $i < $nbFiles; $i++)  {
            
            // Récup des particularité du fichier dans les propriétés temporaires
            $this->_field++;
            $this->_size  = $_FILES['userfile']['size'][$i];
            $this->_type  = $_FILES['userfile']['type'][$i];
            $this->_name  = $_FILES['userfile']['name'][$i];
            $this->_temp  = $_FILES['userfile']['tmp_name'][$i];
            $this->_ext   = strtolower(substr($this->_name, strrpos($this->_name, '.')));
            $this->_error = $_FILES['userfile']['error'][$i];
            
            // On exécute les vérifications demandées
            if ($this->_error == UPLOAD_ERR_OK && is_uploaded_file($_FILES['userfile']['tmp_name'][$i])) {
                
                // Vérification du type mime via la librairie "mime_magic" : on surcharge la propriété _type avec le type renvoyé par la fonction mime_content_type
                if ($this->phpDetectMimeType === self::CST_UPL_HEADER_MIMETYPE) {
                    $this->_type = mime_content_type($_FILES['userfile']['tmp_name'][$i]);
                }
                
                // Vérification du type mime via la librairie "file_info" : on surcharge la propriété _type avec le type renvoyé par la fonction fileinfo()
                else if ($this->phpDetectMimeType === self::CST_UPL_HEADER_FILEINFO) {
                    
                    $fInfo = new finfo(FILEINFO_MIME, $this->magicfile);
                    
                    // La classe retourne une chaine de type "mime; charset". Seul la partie mime nous intéresse.
                    $mime = explode(';', $fInfo->file($_FILES['userfile']['tmp_name'][$i]));
                    
                    $this->_type = trim($mime[0]);
                    
                    unset($fInfo, $mime);
                }
                
                // Vérification des erreurs suplémentaires détectées par la classe
                if (!$this->CheckSecurity() || !$this->CheckMimeType() || !$this->CheckExtension() || !$this->CheckImg()) {
                    continue;
                }                
            }
            else {
                // Erreur retournée par PHP
                $this->AddError($this->_error);
                continue;
            }
            
            // Le fichier a passé toutes les vérifications, on procède à l'écriture
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
     * @param string $temp        extension du fichier précédée d'un point
     * @param string $num_fied    position du champ dans le formulaire à compter de 1
     * @return bool               true/false => succes/erreur
     */
    private function WriteFile($name, $type, $temp, $ext, $num_field) {
        
        $new_filename = null;
        
        if (is_uploaded_file($temp)) {
            
            // Nettoyage du nom original du fichier
            $new_filename = (Empty($this->Filename)) ? $this->CleanFileName(substr($name, 0, strrpos($name, '.'))) : $this->Filename;
            
            // Ajout préfixes / suffixes + extension :
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
            
            // Informations pouvant être utiles au développeur (si le fichier a pu être copié)
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
                $this->Error('move_uploaded_file() a généré une erreur. Vérifiez les droits d\'écriture du répertoire temporaire d\'upload [' . @ini_get('upload_tmp_dir') . '] et celui du répertoire de destination [' . $this->DirUpload . '].');
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
     * Vérifie si le fichier passé en paramètre existe déjà dans le répertoire DirUpload
     * 
     * @access private
     * @return bool
     */
    private function AlreadyExist($file) {
        return (file_exists($this->DirUpload . $file));
    }
    
    
    
    /**
     * Vérifie la hauteur/largeur d'une image
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
     * Vérifie l'extension des fichiers suivant celles précisées dans $Extension
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
     * Vérifie l'entête des fichiers suivant ceux précisés dans $MimeType
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
        
        // Déprécié. Gardé pour compatibilité.
        global $UploadError;
        
        $positionnerEnErreur = true;
        
        switch ($code_erreur) {
            
            case self::CST_UPL_ERR_NONE :
               $positionnerEnErreur = false;
            break;
            
            case '' :
                $msg = 'Exception levée mais non décelée pour le document %FILENAME%.';
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
     * Vérifie les critères de la politique de sécurité
     * OV : 26/10/07 => déprécié.
     * 
     * @access private
     * @return bool
     */
    private function CheckSecurity() {
        
        // Bloque tous les fichiers executables, et tous les fichiers php pouvant être interprété mais dont l'entête ne peut les identifier comme étant dangereux
        if ($this->SecurityMax === true && ereg ('application/octet-stream', $this->_type) || preg_match("/.php$|.inc$|.php3$/i", $this->_ext)) {
            $this->AddError(self::CST_UPL_ERR_UNSAFE_FILE);
            return false;
        }
        
        return true;
    }
    
    
    
    /**
     * Vérifie et formate le chemin de destination :
     *     - définit comme rep par défaut celui de la classe
     *     - teste l'existance du répertoire et son accès en écriture
     * @access private
     */
    private function CheckDirUpload() {
        
        // Si aucun répertoire n'a été précisé, on prend celui de la classe
        if (Empty($this->DirUpload)) $this->DirUpload = dirname(__FILE__);
        
        $this->DirUpload = $this->FormatDir($this->DirUpload);
        
        // Le répertoire existe?
        if (!is_dir($this->DirUpload)) $this->Error('Le répertoire de destination spécifiée par la propriété DirUpload n\'existe pas.');
        
        // Anciennement, le test sur le droit en écriture était géré via la fonction is_writeable() ici.
        // Malheureusement, pour des raisons inconnus, ce test pouvait généré une erreur alors que le répertoire de destination était correctement configuré (Windows Server 2003).
        // Le test est finalement délocalisé lors de l'écriture du fichier via la fonction move_uploaded_file().
    }
    
    
    
    /**
     * Formate le répertoire passé en paramètre
     * - convertit un chemin relatif en chemin absolu
     * - ajoute si besoin le dernier slash (ou antislash suivant le système)
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
     * Formate la chaine passée en paramètre en nom de fichier standard (pas de caractères spéciaux ni d'espaces)
     * @access private
     * @param  string $str   chaine à formater
     * @return string        chaine formatée
     */
    private function CleanFileName($str) {
        
        $return = '';
        
        for ($i=0; $i <= strlen($str)-1; $i++) {
            if (eregi('[a-z]',$str{$i}))              $return .= $str{$i};
            elseif (eregi('[0-9]', $str{$i}))         $return .= $str{$i};
            elseif (ereg('[àâäãáåÀÁÂÃÄÅ]', $str{$i})) $return .= 'a';
            elseif (ereg('[æÆ]', $str{$i}))           $return .= 'a';
            elseif (ereg('[çÇ]', $str{$i}))           $return .= 'c';
            elseif (ereg('[éèêëÉÈÊËE]', $str{$i}))    $return .= 'e';
            elseif (ereg('[îïìíÌÍÎÏ]', $str{$i}))     $return .= 'i';
            elseif (ereg('[ôöðòóÒÓÔÕÖ]', $str{$i}))   $return .= 'o';
            elseif (ereg('[ùúûüÙÚÛÜ]', $str{$i}))     $return .= 'u';
            elseif (ereg('[ýÿÝŸ]', $str{$i}))         $return .= 'y';
            elseif (ereg('[ ]', $str{$i}))            $return .= '_';
            elseif (ereg('[.]', $str{$i}))            $return .= '_';
            else                                      $return .= $str{$i};
        }
        
        return utf8_encode(str_replace(array('\\', '/', ':', '*', '?', '"', '<', '>', '|'), '', $return));
    }
    
    
    
    /**
     * Conversion du poids maximum d'un fichier exprimée en Ko en octets
     * @access private
     */
    private function SetMaxFilesize() {
        (is_numeric($this->MaxFilesize)) ? $this->MaxFilesize = $this->MaxFilesize * 1024 : $this->Error('la propriété MaxFilesize doit être une valeur numérique');
    }
    
    
    
    /**
     * Crée les champs de type fichier suivant la propriété Fields dans un tableau $Field. Ajoute le contenu de FieldOptions aux champs.
     * @access private
     */
    private function CreateFields() {
        
        if (!is_int($this->Fields)) {
            $this->Error('la propriété Fields doit être un entier');
        }
        
        for ($i=0; $i <= $this->Fields; $i++) {
            if ($i == 0)  $this->Field[] = ($this->xhtml) ? '<input type="hidden" name="MAX_FILE_SIZE" value="'. $this->MaxFilesize .'" />' : '<input type="hidden" name="MAX_FILE_SIZE" value="'. $this->MaxFilesize .'">';
            else          $this->Field[] = ($this->xhtml) ? '<input type="file" name="userfile[]" '. $this->FieldOptions .'/>'              : '<input type="file" name="userfile[]" '. $this->FieldOptions .'>';
        }
    }
    
    
    
    /**
     * Vérifie la configuration de la classe.
     * @access private
     */
    private function CheckConfig() {
        
        if (!version_compare(phpversion(), self::CST_UPL_PHP_VERSION)) {
            $this->Error('Version PHP minimale requise : ' . self::CST_UPL_PHP_VERSION . '.');
        }
        
        if (ini_get('file_uploads') != 1) {
            $this->Error('la configuration du serveur ne vous autorise pas à faire du transfert de fichier. Vérifiez la propriété [file_uploads] du fichier [php.ini].');
        }
        
        if (!is_string($this->Extension)) $this->Error('la propriété Extension est mal configurée.');
        if (!is_string($this->MimeType))  $this->Error('la propriété MimeType est mal configurée.');
        if (!is_string($this->Filename))  $this->Error('la propriété Filename est mal configurée.');
        if (!is_bool($this->Required))    $this->Error('la propriété Required est mal configurée.');
        if (!is_bool($this->SecurityMax)) $this->Error('la propriété SecurityMax est mal configurée.');
        
        if ($this->WriteMode != self::CST_UPL_WRITE_COPY && $this->WriteMode != self::CST_UPL_WRITE_ERASE && $this->WriteMode != self::CST_UPL_WRITE_IGNORE) {
            $this->Error('la propriété WriteMode est mal configurée.');
        }
                
        $this->CheckImgPossibility();
        $this->CheckDirUpload();
        
        // Vérification de la propriété $phpDetectMimeType.
        if (!is_int($this->phpDetectMimeType) || ($this->phpDetectMimeType != self::CST_UPL_HEADER_BROWSER && $this->phpDetectMimeType != self::CST_UPL_HEADER_FILEINFO && $this->phpDetectMimeType != self::CST_UPL_HEADER_MIMETYPE)) {
            $this->Error('la propriété phpDetectMimeType est mal configurée.');       
        }
        else if ($this->phpDetectMimeType === self::CST_UPL_HEADER_MIMETYPE) {
            $this->loadMimeTypeLib();
        }
        else if ($this->phpDetectMimeType === self::CST_UPL_HEADER_FILEINFO) {
            $this->loadPECLInfoLib();
        }
    }
    
    
    
    /**
     * Vérifie les propriétés ImgMaxWidth/ImgMaxHeight
     * @access private
     */
    private function CheckImgPossibility() {
        if (!Empty($this->ImgMaxWidth)  && !is_numeric($this->ImgMaxWidth))  $this->Error('la propriété ImgMaxWidth est mal configurée.');
        if (!Empty($this->ImgMaxHeight) && !is_numeric($this->ImgMaxHeight)) $this->Error('la propriété ImgMaxHeight est mal configurée.');
        if (!Empty($this->ImgMinWidth)  && !is_numeric($this->ImgMinWidth))  $this->Error('la propriété ImgMinWidth est mal configurée.');
        if (!Empty($this->ImgMinHeight) && !is_numeric($this->ImgMinHeight)) $this->Error('la propriété ImgMinHeight est mal configurée.');
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
            trigger_error('Impossible de charger la librairie ' . self::CST_UPL_EXT_MIMEMAGIC . '(http://fr3.php.net/manual/fr/ref.mime-magic.php). La vérification des entêtes de fichiers se fera par le biais des informations retournées par la navigateur.', E_USER_WARNING);
            $this->phpDetectMimeType = self::CST_UPL_HEADER_BROWSER;
            return false;
        }
        
        return true;
    }
    
    
    
    /** 
     * Essaie de charger la librairie PECL.
     * Note : impossible d'activer à la volée cette extension.
     * 
     * @access  private
     * @return  bool
     */
    private function loadPECLInfoLib() {
        
        if(!extension_loaded(self::CST_UPL_EXT_FILEINFO)) {
            trigger_error('Impossible de charger la librairie ' . self::CST_UPL_EXT_FILEINFO . ' (http://fr3.php.net/manual/fr/ref.fileinfo). La vérification des entêtes de fichiers se fera par le biais des informations retournées par la navigateur.', E_USER_WARNING);
            $this->phpDetectMimeType = self::CST_UPL_HEADER_BROWSER;
            return false;
        }
        
        $this->magicfile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'mime_magic' . DIRECTORY_SEPARATOR . 'magic';
        
        if (!is_file($this->magicfile)) {
            trigger_error('Impossible de charger le fichier "magic" nécéssaire à la librairie FileInfo. La vérification des entêtes de fichiers se fera par le biais des informations retournées par la navigateur.', E_USER_WARNING);
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