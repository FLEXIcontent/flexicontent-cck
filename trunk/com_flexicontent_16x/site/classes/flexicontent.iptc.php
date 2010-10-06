<?php
defined( '_JEXEC' ) or die( 'Restricted access' );
/**
 * @version 1.5 stable $Id: flexicontent.iptc.php 171 2010-03-20 00:44:02Z emmanuel.danan $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/* INFOS SUR LE FICHIER
Nom : iptc.class.php
Rôle : contient la classe permettant de gérer les IPTC d'un fichier image
Développeur(s) : Arica Alex, Thies C. Arntzen

FIN INFOS SUR LE FICHIER 

INFOS SUR LA CLASSE 'class_iptc'

    REFERENCES : Développée le 04 Octobre 02 par Arica Alex avec l'aide de Thies C. Arntzen
    
    ROLE : permet de manipuler les iptc d'une image

    VARIABLES :
        -    $h_codesIptc
        -    $h_cheminFichier
        -    $h_iptcData

    METHODES :
        -    fct_lireIPTC
        -    fct_ecrireIPTC
        -    fct_iptcMaketag

FIN INFOS SUR LA CLASSE 
*/

class class_IPTC
{
    /* VARIABLES statics */
    
    var $h_codesIptc; /* $h_codesIptc : (tableau associatif) contient les codes des champs IPTC associés à un libellé */
    var $h_cheminImg; /* $h_cheminImg : (chaine) contient le chemin complet du fichier d'image */
    var $h_iptcData;  /* $h_iptcData  : (chaine) contient les données encodées de l'iptc de l'image */
    
    /* FIN VARIABLES statics
    
-------------------------------------------------------------------------------------------------------

    INFOS SUR LA FONCTION

        ROLE : constructeur
        FONCTION : class_IPTC($cheminImg)
        DESCRIPTION DES PARAMETRES : 
            -    $cheminImg  = (chaine) le chemin complet du fichier d'image à traiter
        
    FIN INFOS SUR LA FONCTION */
    
    function class_IPTC($cheminImg)
    {
    
            // Inititalisations
            
                    // Les valeurs IPTC pouvant être manipulées
                    $this -> h_codesIptc = array("005" => "Nom de l'objet",
                                                 "007" => "Statut Éditorial",
                                                 "010" => "Priorité",
                                                 "015" => "Catégorie",
                                                 "020" => "Catégorie Supplémentaire",
                                                 "022" => "Identificateur",
                                                 "025" => "Mots-Clés",
                                                 "030" => "Date de disponibilité",
                                                 "035" => "Heure de disponibilité",
                                                 "040" => "Instructions spéciales",
                                                 "045" => "Service de référence",
                                                 "047" => "Date de référence",
                                                 "050" => "Numéro de référence",
                                                 "055" => "Created Date",
                                                 "060" => "Created Time",
                                                 "065" => "Originating Program",
                                                 "070" => "Program Version",
                                                 "075" => "Object Cycle",
                                                 "080" => "Byline",
                                                 "085" => "Byline Title",
                                                 "090" => "City",
                                                 "095" => "Province State",
                                                 "100" => "Country Code",
                                                 "101" => "Country",
                                                 "103" => "Original Transmission Reference",
                                                 "105" => "Headline",
                                                 "110" => "Credit",
                                                 "115" => "Source",
                                                 "116" => "Copyright String",
                                                 "120" => "Caption",
                                                 "121" => "Local Caption",
                                                 "122" => "Local Caption");

                                                 
                    // On enregistre le chemin de l'image à traiter
                            $this -> h_cheminImg = $cheminImg;
                    
                            
                    // On extrait les données encodées de l'iptc
                            getimagesize($this -> h_cheminImg, $info);
                            $this -> h_iptcData = $info["APP13"];
                    
    }
    
    /* FIN FONCTION class_IPTC();

-------------------------------------------------------------------------------------------------------
    
    INFOS SUR LA FONCTION

        ROLE : lit les IPTC d'une image et les renvoie dans un tableau associatif
        FONCTION : fct_lireIPTC()
        TYPE RETOURNE : chaine sous forme de tableau associatif
        
    FIN INFOS SUR LA FONCTION */
  
    function fct_lireIPTC()
    {
            $tblIPTC = iptcparse($this -> h_iptcData);

            while( (is_array($tblIPTC)) && (list($codeIPTC, $valeurIPTC) = each($tblIPTC)) )
            {
                    $codeIPTC = str_replace("2#", "", $codeIPTC);
                    
                    if( ($codeIPTC != "000") && ($codeIPTC != "140") )
                    {
                            while(list($index, ) = each($valeurIPTC))
                            {
                                    $lesIptc[$codeIPTC] .= $valeurIPTC[$index].$retourLigne;
                                    $retourLigne = "\n";
                            }
                    }
            }
            
            if(is_array($lesIptc)) return $lesIptc; else return false;
    }

    /* FIN FONCTION fct_lireIPTC();
    
    
-------------------------------------------------------------------------------------------------------
    
    INFOS SUR LA FONCTION

        ROLE : écrit des IPTC dans le fichier image
        FONCTION : fct_ecrireIPTC()
        DESCRIPTION DES PARAMETRES : 
            -   $tblIPTC_util         =     (tableau associatif) contient les codes des champs IPTC à modifier associés leur valeur
            -    $cheminImgAModifier =    (chaine) stocke le chemin de l'image dont l'IPTC est à modifier ; s'il est null
                                                 le chemin sera celui contenu dans '$this -> h_cheminImg'
        TYPE RETOURNE : booléen
        
    FIN INFOS SUR LA FONCTION */
  
    function fct_ecrireIPTC($tblIPTC_util, $cheminImgAModifier = "")
    {
                
            // La tableau devant contenir des IPTC est vide ou n'est pas un tableau associatif
                    if( (empty($tblIPTC_util)) || (!is_array($tblIPTC_util)) ) return false;
                    
                    
            // Si le chemin de l'image à modifier est vide alors on lui spécifie le chemin par défaut
                    if(empty($cheminImgAModifier)) $cheminImgAModifier = $this -> h_cheminImg;
        
                    
            // On récupère l'IPTC du fichier image courant
                    $tblIPTC_old = iptcparse($this -> h_iptcData);
                    
                    
            // On prélève le tableau contenant les codes et les valeurs des IPTC de la photo
                    while(list($codeIPTC, $codeLibIPTC) = each($this -> h_codesIptc))
                    {                
                                    
                            // On teste si les données originelles correspondant au code en cours sont présents
                                    if (is_array($tblIPTC_old["2#".$codeIPTC])) $valIPTC_new = $tblIPTC_old["2#".$codeIPTC];
                                    else $valIPTC_new = array();
                            
                                    
                            // On remplace les valeurs des IPTC demandées
                                    if (is_array($tblIPTC_util[$codeIPTC]))
                                    {
                                            if (count($tblIPTC_util[$codeIPTC])) $valIPTC_new = $tblIPTC_util[$codeIPTC];
                                        
                                    }else{
                                        
                                            $val = trim(strval($tblIPTC_util[$codeIPTC]));
                                            if (strlen($val)) $valIPTC_new[0] = $val;
                                    }


                            // On crée un nouveau iptcData à partir de '$tblIPTC_new' qui contient le code et la valeur de l'IPTC
                                    foreach($valIPTC_new as $val)
                                    {
                                            $iptcData_new .= $this -> fct_iptcMaketag(2, $codeIPTC, $val);
                                    }
                            
                    }
                    
                    
            /*    A partir du nouveau iptcData contenu dans '$iptcData_new' on crée grâce à la fonction 'iptcembed()'
                le contenu binaire du fichier image avec le nouveau IPTC inclu */
                    $contenuImage = iptcembed($iptcData_new, $this -> h_cheminImg);
            
                    
            // Ecriture dans le fichier image
                    $idFichier = fopen($cheminImgAModifier, "wb");
                    fwrite($idFichier, $contenuImage);
                    fclose($idFichier);
                    
                    
            return true;
            
    }

    /* FIN FONCTION fct_ecrireIPTC(); 
    
-------------------------------------------------------------------------------------------------------
    
    INFOS SUR LA FONCTION

        ROLE : permet de transformer une valeur de d'IPTC (code + valeur) en iptcData
        AUTEUR : Thies C. Arntzen
        FONCTION : fct_iptcMaketag($rec, $dat, $val)
        DESCRIPTION DES PARAMETRES : 
            -   $rec = (entier) toujours à mettre à 2
            -    $dat = (chaine) le code de l'IPTC (de type '110' et non '2#110')
            -    $val = (chaine) la valeur de l'IPTC
        TYPE RETOURNE : booléen
        
    FIN INFOS SUR LA FONCTION */
    
    function fct_iptcMaketag($rec, $dat, $val)
    {
            $len = strlen($val);
            if ($len < 0x8000) 
            return chr(0x1c).chr($rec).chr($dat).
            chr($len >> 8).
            chr($len & 0xff).
            $val; 
            else 
            return chr(0x1c).chr($rec).chr($dat).
            chr(0x80).chr(0x04).
            chr(($len >> 24) & 0xff).
            chr(($len >> 16) & 0xff).
            chr(($len >> 8 ) & 0xff).
            chr(($len ) & 0xff).
            $val;
    }
    
    // FIN FONCTION fct_iptcMaketag();
    
    
    
    
    
}

/*  Fin class_IPTC
 
  
 
  
  
  

-------------------------------------------------------------------------------------------------------
-------------------------------------------------------------------------------------------------------
-------------------------------------------------------------------------------------------------------
-------------------------------------------------------------------------------------------------------
-------------------------------------------------------------------------------------------------------
*/
?> 