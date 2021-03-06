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

require_once (RAPTOR_GLUE_MODULE_PATH . '/functions/protocol.inc');

module_load_include('php', 'raptor_datalayer', 'config/Choices');

require_once ('FormHelper.php');
require_once ('ProtocolLibPageHelper.php');

/**
 * This class returns the Admin Information input content
 *
 * @author Frank Font of SAN Business Consultants
 */
class EditProtocolLibPage
{
    private $m_oPageHelper = null;
    private $m_protocol_shortname = null;

     //Call same function as in EditUserPage here!
    function __construct($protocol_shortname)
    {
        if(!isset($protocol_shortname) || is_numeric($protocol_shortname))
        {
            die("Missing or invalid protocol_shortname value on edit form = [" . $protocol_shortname . ']');
        }
        $this->m_protocol_shortname = $protocol_shortname;
        $this->m_oPageHelper = new \raptor\ProtocolLibPageHelper();

        //drupal_set_message('Editing protocol shortname=['.$protocol_shortname.']');
    }

    /**
     * Get the values to populate the form.
     * @param type $sProtocolName the user id
     * @return type result of the queries as an array
     */
    function getFieldValues()
    {
        $myvalues = $this->m_oPageHelper->getFieldValues($this->m_protocol_shortname);
        return $myvalues;
    }

    /**
     * Validate the proposed values.
     * @param type $form
     * @param type $myvalues
     * @return true if no validation errors detected
     */
    function looksValid($form, $myvalues)
    {
        return $this->m_oPageHelper->looksValid($form, $myvalues, 'E');
    }

    /**
     * Write the values into the database.
     * @param type associative array where 'username' MUST be one of the values so we know what record to update.
     */
    function updateDatabase($form, $myvalues)
    {

        $bHappy = TRUE; //Assume no problems.
        $protocol_shortname = $this->m_protocol_shortname;
        try
        {
            //First copy values for the existing record into the raptor_protocol_lib_replaced table
            $this->m_oPageHelper->copyProtocolLibToReplacedTable($protocol_shortname);

            //Now prepare the change the existing record.
            $yn_attribs = isset($myvalues['yn_attribs']) ? $myvalues['yn_attribs'] : array();
            $contrast_yn = (isset($yn_attribs['C']) && $yn_attribs['C'] === 'C') ? 1 : 0;
            $image_guided_yn = (isset($yn_attribs['IG']) && $yn_attribs['IG'] === 'IG') ? 1 : 0;
            $radioisotope_yn = (isset($yn_attribs['RI']) && $yn_attribs['RI'] === 'RI') ? 1 : 0;
            $sedation_yn = (isset($yn_attribs['S']) && $yn_attribs['S'] === 'S') ? 1 : 0;
            $active_yn = 1;
            $multievent_yn = 0;
            $updated_dt = date("Y-m-d H:i", time());
            $nUpdated = db_update('raptor_protocol_lib')->fields(array(
                'name' => $myvalues['name'],
                'version' => $myvalues['version'],
                'modality_abbr' => $myvalues['modality_abbr'],
                'service_nm' => '', //hardcoded as empty string always for now $myvalues['service_nm'],
                'lowerbound_weight' => $myvalues['lowerbound_weight'],
                'upperbound_weight' => $myvalues['upperbound_weight'],
                'image_guided_yn' => $image_guided_yn,
                'contrast_yn' => $contrast_yn,
                'radioisotope_yn' => $radioisotope_yn,
                'sedation_yn' => $sedation_yn,
                'multievent_yn' => $multievent_yn,
                'filename' => $myvalues['filename'],
                'active_yn' => $active_yn,
                'updated_dt' => $updated_dt,
                ))
           ->condition('protocol_shortname', $protocol_shortname, '=')
           ->execute();
           
        }
        catch(\Exception $ex)
        {
           error_log("Failed to save protocol into database!\n" . print_r($myvalues, TRUE) . '>>>'. print_r($ex, TRUE));
           drupal_set_message('Failed to save the new protocol because ' . $ex, 'error');
           return 0;
        }
        //Now write all the child records
        $bHappy =$this->m_oPageHelper->writeChildRecords($protocol_shortname, $myvalues, TRUE);
        
        //Returns 1 if everything was okay
        drupal_set_message('Saved changes to protocol ' . $protocol_shortname);
  
        return $bHappy;
    }


    /**
     * @return array of all option values for the form
     */
    function getAllOptions()
    {
        return $this->m_oPageHelper->getAllOptions();
    }

    /**
     * Get all the form contents for rendering
     * @return type renderable array
     */
    function getForm($form, &$form_state, $disabled, $myvalues)
    {
        $form = $this->m_oPageHelper->getForm('E',$form, $form_state, FALSE, $myvalues, 'protocol_container_styles');

        $form['data_entry_area1']['action_buttons'] = array(
            '#type' => 'item',
            '#prefix' => '<div class="raptor-action-buttons">',
            '#suffix' => '</div>',
            '#tree' => TRUE,
        );
        $form['data_entry_area1']['action_buttons']['create'] = array('#type' => 'submit'
                , '#attributes' => array('class' => array('admin-action-button'))
                , '#value' => t('Save Protocol Updates')
                , '#validate' => array('raptor_glue_editprotocollib_form_builder_customvalidate')
                , '#disabled' => $disabled
            );

        $form['data_entry_area1']['action_buttons']['cancel'] = array('#type' => 'item'
                , '#markup' => '<input class="admin-cancel-button" id="user-cancel" type="button" value="Cancel" data-redirect="/drupal/worklist?dialog=manageProtocolLib">');

        return $form;
    }
}
