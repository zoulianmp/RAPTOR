<?php
/**
 * ------------------------------------------------------------------------------------
 * Created by SAN Business Consultants for RAPTOR phase 2
 * Open Source VA Innovation Project 2011-2014
 * VA Innovator: Dr. Jonathan Medverd
 * SAN Implementation: Andrew Casertano, Frank Font, et al
 * Contacts: acasertano@sanbusinessconsultants.com, ffont@sanbusinessconsultants.com
 * ------------------------------------------------------------------------------------
 * 
 */ 


namespace raptor;

module_load_include('php', 'raptor_datalayer', 'config/Choices');
require_once ("FormHelper.php");
require_once ('ListsPageHelper.php');

/**
 * This class returns the Admin Information input content
 *
 * @author Frank Font of SAN Business Consultants
 */
class EditListExamRoomPage
{

    private $m_oPageHelper      = null;
    private $m_sTablename       = 'raptor_schedule_location';
    private $m_aFieldNames      = array('location_tx','description_tx');
    private $m_aRequiredCols    = array(true,       true);
    private $m_aMaxLenCols      = array(16,         100);
    private $m_aDataTypeCols    = array('t',        't');
    private $m_aHelpText        = array('Location','Description Text');
    private $m_aOrderBy         = array('location_tx');    
    
    private $mycount = 0;
    
     //Call same function as in EditUserPage here!
    function __construct()
    {
        $this->m_oPageHelper = new \raptor\ListsPageHelper();
    }

    /**
     * Get the values to populate the form.
     * @return type result of the queries as an array
     */
    function getFieldValues()
    {
        $this->mycount++;
        
        $tablename = $this->m_sTablename;
        $aFieldNames = $this->m_aFieldNames;
        $aOrderBy = $this->m_aOrderBy;
        $myvalues = $this->m_oPageHelper->getFieldValues($tablename, $aFieldNames, $aOrderBy);
        $myvalues['formmode'] = 'E';
        
        //die('look at the values' . print_r($myvalues,true));
        return $myvalues;
    }

    /**
     * Write the values into the database.
     * Return 1 if all okay, else return 0.
     */
    function updateDatabase($myvalues)
    {
        if(!isset($myvalues['raw_list_rows']))
        {
            die("Cannot update user record because missing raw_list_rows in array!\n" . var_dump($myvalues));
        }

        $tablename = $this->m_sTablename;
        $aFieldNames = $this->m_aFieldNames;
        $aOrderBy = $this->m_aOrderBy;
        $aRequiredCols = $this->m_aRequiredCols;
        $aMaxLenCols = $this->m_aMaxLenCols;
        $aDataTypeCols = $this->m_aDataTypeCols;
        
        $aRawRows = $this->m_oPageHelper->parseRawText($myvalues['raw_list_rows']);
        $result = $this->m_oPageHelper->parseRows($aRawRows, $aRequiredCols, $aMaxLenCols, $aDataTypeCols);
        
        $errors = $result['errors'];
        if(count($errors) > 0)
        {
            if(count($errors) > 1)
            {
                form_set_error("raw_list_rows",'<ol><li>'.implode('<li>', $errors).'</ol>');
            } else {
                form_set_error("raw_list_rows",$errors[0]);
            }
            /*
            foreach($errors as $error)
            {
                form_set_error("raw_list_rows",$error);
            }
             * 
             */
            return 0;
        } else {
            $nRows = $this->m_oPageHelper->writeValues($tablename, $aFieldNames, $result['parsedrows']);
            return 1;
        }
    }
    
    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form, &$form_state, $disabled, $myvalues)
    {
        $form = $this->m_oPageHelper->getForm($form, $form_state, $disabled, $myvalues, $this->m_aHelpText);
        return $form;
    }
}
