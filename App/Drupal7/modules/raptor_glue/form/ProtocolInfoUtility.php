<?php
/**
 * @file
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
module_load_include('php', 'raptor_datalayer', 'config/ListUtils');
module_load_include('php', 'raptor_datalayer', 'core/data_worklist');
module_load_include('php', 'raptor_datalayer', 'core/data_dashboard');
module_load_include('php', 'raptor_datalayer', 'core/data_protocolsupport');

require_once (RAPTOR_GLUE_MODULE_PATH . '/functions/protocol_ajax.inc');
require_once ('FormHelper.php');

/**
 * Utilities for the ProtocolInfo form content.
 *
 * @author Frank Font of SAN Business Consultants
 */
class ProtocolInfoUtility
{
    private $m_oContext = NULL;
    private $m_oTT = NULL;
    
    function __construct()
    {
        $this->m_oContext = \raptor\Context::getInstance();
        $this->m_oTT = new \raptor\TicketTrackingData();
    }
    
    /**
     * Return markup containing all the collaboration notes associated 
     * with the provided search criteria
     */
    public function getPreviousNotesMarkup($tablename,$nSiteID,$nIEN,$extraClassname='')
    {
        
        $prev_notes_tx = NULL;

        //Get app existing notes
        $query = db_select($tablename, 'n');
        $query->join('raptor_user_profile', 'u', 'n.author_uid = u.uid');
        $query->fields('n', array('created_dt', 'notes_tx'));
        $query->fields('u', array('username','role_nm','usernametitle','firstname','lastname','suffix','prefemail','prefphone'))
            ->condition('siteid', $nSiteID,'=')
            ->condition('IEN', $nIEN,'=')
            ->orderBy('created_dt', 'ASC');
        $result = $query->execute();
        while($record = $result->fetchAssoc())
        {
            //Create the markup.
            $fullname = trim($record['usernametitle'] . ' ' . $record['firstname'] . ' ' . $record['lastname'] . ' ' . $record['suffix']);
            $prev_notes_tx .= '<div class="existing-note '.$extraClassname.'">'
                    . '<span class="datetime">' . $record['created_dt'] . '</span> ' 
                    . '<span class="author-name">' . $fullname  . '</span> ' 
                    . '<span class="author-phone">' . $record['prefphone'] . '</span> ' 
                    . '<span class="author-email">' . $record['prefemail'] . '</span> '  
                    . '<div class="note-text">' . $record['notes_tx'] . '</div> '  
                    . '</div>';
        }
        
        return $prev_notes_tx;
    }
    
    /**
     * Return markup containing all the scheduluer notes associated 
     * with the provided search criteria
     */
    public function getSchedulerNotesMarkup($nSiteID,$nIEN)
    {
        
        $scheduler_notes = NULL;

        $query = db_select('raptor_schedule_track', 'n');
        $query->fields('n');
        $query->condition('siteid',$nSiteID,'=');
        $query->condition('IEN',$nIEN,'=');
        $query->orderBy('scheduled_dt');
        $result = $query->execute();
        while($record = $result->fetchAssoc())
        {
            $scheduled_dt = $record['scheduled_dt'];
            if(isset($scheduled_dt))
            {
                $dt = new \DateTime($scheduled_dt);
                $event_date_tx = $dt->format('m/d/Y');
                $event_starttime_tx = $dt->format('H:i');
                $details = ' ('.$event_date_tx.'@'.$event_starttime_tx.')';
            } else {
                $details = '';
            }
            $fullname = 'Scheduler';
            $sClassText = 'existing-scheduler-note';
            if($record['notes_critical_yn'] == 1)
            {
                $sClassText .= ' critical-note';
            }
            $scheduler_notes .= "\n".'<div class="existing-note '.$sClassText.'">'
                    . '<span class="datetime">' . $record['created_dt'] . '</span> ' 
                    . '<span class="author-name">'.$fullname.'</span> ' 
                    . '<span class="scheduled-time-details">'.$details.'</span> ' 
                    . '<div class="note-text">' . $record['notes_tx'] . '</div> '  
                    . '</div>';
        }
        
        return $scheduler_notes;
    }
    
    /**
     * Return markup containing all the collaboration notes associated 
     * with the provided search criteria
     */
    public function getCollaborationNotesMarkup($nSiteID,$nIEN)
    {
        
        $sNotesMarkup = NULL;

        $query = db_select('raptor_ticket_collaboration', 'n');
        $query->join('raptor_user_profile','u','n.requester_uid = u.uid');
        $query->fields('n',array('requester_uid','requested_dt','requester_notes_tx','active_yn'));
        $query->fields('u',array('username', 'usernametitle', 'firstname', 'lastname', 'suffix'));
        $query->condition('n.siteid',$nSiteID,'=');
        $query->condition('n.IEN',$nIEN,'=');
        $query->orderBy('n.requested_dt');
        $result = $query->execute();
        while($record = $result->fetchAssoc())
        {
            $fullname = trim($record['usernametitle'] . ' ' . $record['firstname'] . ' ' . $record['lastname'] . ' ' . $record['suffix']);
            $sClassText = 'existing-collabrequest-note';
            if($record['active_yn'] == 1)
            {
                $sClassText .= ' active-note';
            }
            $sNotesMarkup .= '<div class="existing-note '.$sClassText.'">'
                    . '<span class="datetime">' . $record['requested_dt'] . '</span> ' 
                    . '<span class="context-indicator">Collaboration request from</span> ' 
                    . '<span class="author-name">'.$fullname.'</span> ' 
                    . '<div class="note-text">' . $record['requester_notes_tx'] . '</div> '  
                    . '</div>';
        }
        
        return $sNotesMarkup;
    }

    /**
     * NULL means no opinion
     */
    function inferModalityFromPhrase($phrase)
    {
        $haystack = strtoupper(trim($phrase));
        if(strlen($haystack) > 2)
        {
            $ma = substr($haystack,0,2);
            if($haystack[2] !== ' ')
            {
                if(strpos($haystack, 'FLUORO') !== FALSE)
                {
                    $ma = 'FL';
                } else
                if(strpos($haystack, 'MRI') !== FALSE)
                {
                    $ma = 'MR';
                }
            }
            return $ma;
        }

        //No clues or confusing indications.
        return NULL;
    }
    
    /**
     * TRUE means yes contrast
     * FALSE means no contrast
     * NULL means no opinion
     */
    function inferContrastFromPhrase($phrase)
    {
        $haystack = strtoupper($phrase);

        //Look for indication of both
        $both_contrast_ind = FALSE; //Assume not both
        $both_contrast_ind[] = 'W&WO CONT';
        $both_contrast_ind[] = 'WITH AND WITHOUT CONT';
        foreach($both_contrast_ind as $needle)
        {
            $p = strpos($haystack, $needle);
            if($p !== FALSE)
            {
                $both_contrast_ind = TRUE;
                break;
            }
        }
        if(!$both_contrast_ind)
        {
            //Look for the NO indicators
            $no_contrast = NULL;
            $no_contrast_ind = array();
            $no_contrast_ind[] = 'W/O CONT';
            $no_contrast_ind[] = 'W/N CONT';
            $no_contrast_ind[] = 'WO CONT';
            $no_contrast_ind[] = 'WN CONT';
            $no_contrast_ind[] = 'NO CONT';
            $no_contrast_ind[] = 'WITHOUT CONT';
            foreach($no_contrast_ind as $needle)
            {
                $p = strpos($haystack, $needle);
                if($p !== FALSE)
                {
                    $no_contrast = TRUE;
                    break;
                }
            }

            //Look for the YES indicators
            $yes_contrast = NULL;
            $yes_contrast_ind = array();
            $yes_contrast_ind[] = 'W CONT';
            $yes_contrast_ind[] = 'WITH CONT';
            $yes_contrast_ind[] = 'W/IV CONT';
            $yes_contrast_ind[] = 'INCLUDE CONT';
            $yes_contrast_ind[] = 'INC CONT';
            foreach($yes_contrast_ind as $needle)
            {
                $p = strpos($haystack, $needle);
                if($p !== FALSE)
                {
                    $yes_contrast = TRUE;
                    break;
                }
            }

            //Return our analysis result.
            if($no_contrast && $yes_contrast === NULL)
            {
                return FALSE;
            }
            if($no_contrast === NULL && $yes_contrast)
            {
                return TRUE;
            }
        }
        
        //No clues or confusing indications.
        return NULL;
    }
    
    function getOverallProtocolDataEntryArea1($sCWFS, &$form_state, $disabled
            , $myvalues
            , $template_json=NULL
            , $clues=NULL)
    {
        $disableAllInput = $disabled || (($sCWFS !== 'AC') && ($sCWFS !== 'RV') && ($sCWFS !== 'CO'));
        $disableChildInput = $disableAllInput 
                || (!isset($myvalues['protocol1_nm']) 
                || trim($myvalues['protocol1_nm']) == '');
        $form = array();
        
        //PROTOCOL 
        if($template_json == NULL)
        {
            if(!$disableChildInput && isset($myvalues['protocol1_nm']) && trim($myvalues['protocol1_nm']) > '')
            {
                module_load_include('php', 'raptor_datalayer', 'core/data_protocolsettings');
                $oPS = new \raptor\ProtocolSettings();    //TODO cache it
                $templatevalues = $oPS->getDefaultValuesStructured($myvalues['protocol1_nm']);
                $template_json = json_encode($templatevalues);
            } else {
                $template_json = '';    //Nothing needed.
            }
        }
        $hiddendatahtml = "\n<div id='protocol-template-data'>"
              . "\n<div id='json-default-values-all-sections'"
              . " style='visibility:hidden; height:0px;'>$template_json</div>\n"
              . "\n</div>";
        $form['hiddenptotocolstuff'] = array('#markup' 
            => $hiddendatahtml);
        
        //Main protocol selection
        $form['protocolinput'][] = $this->getProtocolSelectionElement($form_state
                , $disableAllInput
                , $myvalues
                , TRUE
                , 'protocol1'
                , "A standard protocol from the hospital's radiology notebook."
                , TRUE
                , TRUE
                , $clues);
        
        //Secondary protocol selection
        $form['protocolinput'][] = $this->getProtocolSelectionElement($form_state
                , $disableChildInput
                , $myvalues
                , FALSE
                , 'protocol2'
                , "Select a second protocol only if more than one is needed for this study."
                , FALSE, FALSE);
        
        //Contrast
        $shownow = $this->hasContrastValues($myvalues, $form_state);
        $contrastarea = $this->getOverallSectionCheckboxType($form_state
                , 'contrast', 'Contrast'
                , $disableChildInput
                , $myvalues
                , NULL
                , TRUE
                , $shownow
                , $shownow); 
        $form['protocolinput'][] = $contrastarea;

        //Consent Required
        $shownow = !$disableChildInput;
        $consentarea = $this->getYesNoResetRadioTypeSection('consentreq', 'Consent Required'
            , $disableChildInput
            , $myvalues
            , NULL
            , TRUE
            , $shownow
            , $shownow);
        $form['protocolinput'][] = $consentarea;
        /*
        $form['protocolinput'][] = $this->raptor_form_get_consent($form_state
                , $disableChildInput, $myvalues, null, null);
        */
                
        //Hydration
        $shownow = $this->hasHydrationValues($myvalues);
        $hydrationarea = $this->getOverallSectionRadioType($form_state
                , 'hydration', 'Hydration'
                , $disableChildInput
                , $myvalues
                , NULL, TRUE
            , $shownow
            , $shownow);
        $form['protocolinput'][] = $hydrationarea;
        
        //Sedation
        $shownow = $this->hasSedationValues($myvalues);
        $sedationarea = $this->getOverallSectionRadioType($form_state
                , 'sedation', 'Sedation'
                , $disableChildInput
                , $myvalues
                , NULL, TRUE
            , $shownow
            , $shownow);
        $form['protocolinput'][] = $sedationarea;
        
        //Radioisotope
        $shownow = $this->hasRadioisotopeValues($myvalues);
        $radioisotopearea = $this->getOverallSectionCheckboxType($form_state
                , 'radioisotope', 'Radioisotope'
                , $disableChildInput
                , $myvalues
                , NULL, TRUE
            , $shownow
            , $shownow);
        $form['protocolinput'][] = $radioisotopearea;
        
        //Allergy
        $allergyarea = $this->getYesNoRadioTypeSection('allergy', 'Allergy'
                    , $disableChildInput
                    , $myvalues
                    , NULL
                    , TRUE);
        $form['protocolinput'][] = $allergyarea;
        
        //Claustrophobic
        $claustrophobicarea = $this->getYesNoRadioTypeSection('claustrophobic', 'Claustrophobic'
                    , $disableChildInput
                    , $myvalues
                    , NULL
                    , TRUE);
        $form['protocolinput'][] = $claustrophobicarea;

        return $form;
    }

    function getOverallProtocolDataEntryArea2($sCWFS, &$form_state, $disabled, $myvalues)
    {
        $disableAllInput = $disabled || (($sCWFS !== 'AC') && ($sCWFS !== 'RV') && ($sCWFS !== 'CO'));
        $disableChildInput = $disableAllInput 
                || (!isset($myvalues['protocol1_nm']) || isset($myvalues['protocol1_nm']) == '');
        
        $root = array();
        if(isset($myvalues['prev_protocolnotes_tx']))
        {
            $root["PrevProtocolNotes"] = array(
                '#prefix' => "\n<div class='prev-protocolnotes'>\n",
                '#markup' => $myvalues['prev_protocolnotes_tx'],
                '#suffix' => "\n</div>\n",
            );
        }
        if(isset($myvalues['prev_suspend_notes_tx']))
        {
            $root["PrevSuspendNotes"] = array(
                '#prefix' => "\n<div class='prev-suspend-notes'>\n",
                '#markup' => $myvalues['prev_suspend_notes_tx'],
                '#suffix' => "\n</div>\n",
            );
        }

        $root['ProtocolNotes'] 
                = $this->getNotesSectionMarkup('protocolnotes', 'Protocol Notes'
                , $disableChildInput, $myvalues);

        
        
        return $root;
    }

    function getOverallExamDataEntryArea($sCWFS
            , $protocolValues
            , &$form_state, $disabled, $myvalues)
    {
        $root = array();
        
        $modality_abbr = $protocolValues['modality_abbr'];
        $protocol_shortname = $protocolValues['protocol_shortname'];
        $disableExamInput = $disabled || ($sCWFS !== 'PA');

        if(!$disableExamInput)
        {
            //Always show previous notes first if input is NOT disabled.
            if(isset($myvalues['prev_exam_notes_tx']))
            {
                $root['PrevExamNotes'] = array(
                    '#prefix' => "\n<div class='prev-exam-notes'>\n",
                    '#markup' => $myvalues['prev_exam_notes_tx'],
                    '#suffix' => "\n</div>\n",
                );
            }
        }
        
        if($sCWFS == 'AP')
        {
            //Show the safety checklist in edit mode.
            $root['data_entry_area2']['page_checklist_area1'] 
                    = $this->getPageChecklistArea($form_state, $disabled, $myvalues,'Safety Checklist','SC',$modality_abbr,$protocol_shortname);
        } else if($sCWFS == 'PA' || $sCWFS == 'EC' || $sCWFS == 'QA') {
            //Show the safety checklist in disabled mode.  TODO --- force to answer if they have not answered some checklist items!!!!
            $root['data_entry_area2']['page_checklist_area1'] 
                    = $this->getPageChecklistArea($form_state, TRUE, $myvalues,'Safety Checklist','SC',$modality_abbr,$protocol_shortname);
            $root['exam_data_entry_area1'][]  
                    = $this->getExamDataEntryFields($form_state
                            , $disableExamInput
                            , $myvalues, $protocolValues);
        }

        if($disableExamInput)
        {
            //Always show previous notes LAST if input is disabled.
            if(isset($myvalues['prev_exam_notes_tx']))
            {
                $root['PrevExamNotes'] = array(
                    '#prefix' => "\n<div class='prev-exam-notes'>\n",
                    '#markup' => $myvalues['prev_exam_notes_tx'],
                    '#suffix' => "\n</div>\n",
                );
            }
        }
        
        return $root;
    }

    function getOverallInterpretationDataEntryArea($sCWFS, &$form_state, $disabled, $myvalues)
    {
        $root = array();
        if(isset($myvalues['prev_interpret_notes_tx']))
        {
            $root['PrevInterpretationNotes'] = array(
                '#prefix' => "\n<div class='prev-interpretation-notes'>\n",
                '#markup' => $myvalues['prev_interpret_notes_tx'],
                '#suffix' => "\n</div>\n",
            );
        }
        
        if($sCWFS == 'EC')
        {
            //Only show this when we are in EC mode.
            $disableInput = $disabled || ($sCWFS != 'EC');
            $root['interpretation_data_entry_area1']  = $this->getInterpretationDataEntryFields($form_state, $disableInput, $myvalues);
        }
        return $root;
    }
    
    function getOverallQADataEntryArea($sCWFS, &$form_state, $disabled, $myvalues)
    {
        $root = array();
        if(isset($myvalues['prev_qa_notes_tx']))
        {
            $root['PrevQANotes'] = array(
                '#prefix' => "\n<div class='prev-qa-notes'>\n",
                '#markup' => $myvalues['prev_qa_notes_tx'],
                '#suffix' => "\n</div>\n",
            );
        }
        
        if($sCWFS == 'QA')
        {
            $root['qa_data_entry_area1']  = $this->getQADataEntryFields($form_state, $disabled, $myvalues);
        }
        return $root;
    }
    
    /**
     * We display this section if the section is pre-populated with default values.
     * @param string $section_name name of the section
     * @param boolean $disabled true if disabled, else enabled
     * @param array $defaultvalues the default values for the controls of the section
     * @return type section to add into the renderable array
     */
    function getDefaultValueSubSection($section_name, $disabled, $defaultvalues, $shownow)
    {
        $root = array();
        if(REQUIRE_ACKNOWLEDGE_DEFAULTS === FALSE)
        {
            $root['default_values_grp_'.$section_name.'']['acknowledge_'.$section_name] = array(
                '#type'  => 'hidden',
                '#value' => 'no',
            );
        } else {
            if($disabled)
            {
                //Do NOT show it if section is disabled.
                $shownow = FALSE;
            }
            $root['default_values_grp_'.$section_name.''] = array(
                '#type' => 'container',
                '#attributes' => array(
                        'class' => array('acknowledge-default-value'),
                        'style' => array($shownow ? 'display:inline' : 'display:none' ),
                    ),
            );
            $root['default_values_grp_'.$section_name.'']['acknowledge_'.$section_name] = array(
                '#type'    => 'checkbox',
                '#title' => t('Acknowledge Selected Values'),
                '#description' => t('You are being asked to acknowledge these values because they are currently the default values.'),
                '#disabled' => $disabled,
            );
        }

        return $root;
    }

    /**
     * Get the block of default value controls for a section
     * These sections will show/hide at runtime using AJAX.
     */
    function getDefaultValueControls($section_name, $disabled
            , $defaultvalues=NULL, $require_ack=FALSE)
    {
        $root = array();
        if(REQUIRE_ACKNOWLEDGE_DEFAULTS === FALSE)
        {
            $root[]['require_acknowledgement_for_'.$section_name] = array(
                '#type'  => 'hidden',
                '#value' => 'no',
            );
        } else {
            //Always create the markup, but show it only if there are default values.
            $shownow = $require_ack;
            $root[] = $this->getDefaultValueSubSection($section_name, $disabled, $defaultvalues, $shownow);

            //Create a hidden field with standard Drupal framework ID name so javascript can track required or not.
            $root[]['require_acknowledgement_for_'.$section_name] = array(
                '#type' => 'hidden', 
                '#attributes' => array('id' 
                    => 'edit-require-acknowledgement-for-'.$section_name ),
                '#default_value' => ($require_ack ? 'yes' : 'no'),
            );
        }
        return $root;
    }

    /**
     * Create an input area for a single checklist question.
     * @param array $myvalues
     * @param number $number
     * @param object $item
     * @param boolean $disabled
     * @param boolean $bRequireValue
     * @return renderable array element
     */
    private function getOneChecklistQuestion($myvalues,$title,$number,$aOneQuestion,$disabled,$bRequireValue=TRUE)
    {
        if($title == NULL || trim($title) == '')
        {
            $title = 'Question '.$number;   //Must have a title otherwise validation feedback breaks.
        }
        
        $shortname = $aOneQuestion['question_shortname'];
        $question_tx = $aOneQuestion['question_tx'];
        $comment_prompt_tx = $aOneQuestion['comment_prompt_tx'];
        
        $ask_yes_yn = $aOneQuestion['ask_yes_yn'];
        $ask_no_yn = $aOneQuestion['ask_no_yn'];
        $ask_notsure_yn = $aOneQuestion['ask_notsure_yn'];
        $ask_notapplicable_yn = $aOneQuestion['ask_notapplicable_yn'];

        $trigger_comment_on_yes_yn = $aOneQuestion['trigger_comment_on_yes_yn'];
        $trigger_comment_on_no_yn = $aOneQuestion['trigger_comment_on_no_yn'];
        $trigger_comment_on_notsure_yn = $aOneQuestion['trigger_comment_on_notsure_yn'];
        $trigger_comment_on_notapplicable_yn = $aOneQuestion['trigger_comment_on_notapplicable_yn'];
        
        //drupal_set_message('LOOK>>>'.$number.') '.$question_tx);

        //Look for values associated with currently logged in user.
        $aQuestion = isset($myvalues['questions']['thisuser'][$shortname]) ? $myvalues['questions']['thisuser'][$shortname] : NULL;
        if(!is_array($aQuestion))
        {
            $default_response = NULL;   //IMPORTANT THIS MUST BE NULL instead of empty string else DRUPAL ERRORS!
            $default_comment = NULL;
        } else {
            $default_response = $aQuestion['response'];
            $default_comment = $aQuestion['comment'];
        }
                       
        $element = array();
        $aRadios = array();
        $aOptions = array();
        $showOnValues = '';
        //$showOnValues = '[no][notsure][notapplicable]';
        if($ask_yes_yn == 1)
        {
            $aOptions['yes'] = t('Yes');
            if($trigger_comment_on_yes_yn == 1)
            {
                $showOnValues .= '[yes]';
            }
        }
        if($ask_no_yn)
        {
            $aOptions['no'] = t('No');
            if($trigger_comment_on_no_yn == 1)
            {
                $showOnValues .= '[no]';
            }
        }
        if($ask_notsure_yn)
        {
            $aOptions['notsure'] = t('Not Sure');
            if($trigger_comment_on_notsure_yn == 1)
            {
                $showOnValues .= '[notsure]';
            }
        }
        if($ask_notapplicable_yn)
        {
            $aOptions['notapplicable'] = t('Not Applicable');
            if($trigger_comment_on_notapplicable_yn == 1)
            {
                $showOnValues .= '[notapplicable]';
            }
        }
        $hiddenshowcommentonvalues = 'showcommentonvalues';
        $hiddenshortname = 'shortname';
        $radiosDrupalName = 'response';//.$number;
        $commentDrupalName = 'comment';//$radiosName.'_comment';
        $commentHtmlTagName = 'questions[thisuser]['.$shortname.']['.$commentDrupalName.']'; //Because #tree structure!
        $sTextareaHtmlwrapperId = $commentHtmlTagName.'-wrapper';
        if($default_comment > '')
        {
            //We have comment text so show it.
            $shownow = TRUE;
        } else {
            //Show the comment box only if the buttons say we should.
            if(!isset($myvalues[$radiosDrupalName]))
            {
                $shownow = FALSE;
            } else {
                $value = $myvalues[$radiosDrupalName];
                if(strpos($showOnValues,'['.$value.']') !== FALSE)
                {
                    $shownow = TRUE;
                } else {
                    $shownow = FALSE;
                }
            }
        }
        $aHiddenShowOnValues = array('#type'=>'hidden','#value'=>$showOnValues);
        $aRadios = array(
            '#type' => 'radios',
            '#options' => $aOptions,
            //'#required' => TRUE, //$bRequireValue,
            '#disabled' => $disabled,
            '#attributes' => array(
                'onclick' => 'manageChecklistQuestionComment(this.value,"'.$showOnValues.'","'.$commentHtmlTagName.'");',
             ),
             '#title' => t($title),   //Important to have title otherwise required symbol not shown! 
             '#default_value' => $default_response,
        );
        $aRadios['#attributes']['class'][] = 'question-options';
        $aComment = array(
                    '#type'          => 'textarea',
                    '#prefix'        => "\n".'<div name="'.$sTextareaHtmlwrapperId.'" class="comment-wrapper"'
                                        .' style="' . ($shownow ? 'display:inline' : 'display:none') . '" >',
                    '#suffix'        => "\n".'</div> <!-- End of '.$sTextareaHtmlwrapperId.' -->',
                    '#title'         => t($comment_prompt_tx),
                    '#default_value' => $default_comment,
                    '#disabled'      => $disabled,
                    /*
                    '#attributes' => array(
                        'style' => array($shownow ? 'display:inline' : 'display:none' )
                        ),
                     */
                );
        
        $element[] = array('#markup' => "\n".'<div class="question-block">');
        $element[$hiddenshortname] = array('#type'=>'hidden','#value'=>$shortname);
        $element[$hiddenshowcommentonvalues] = $aHiddenShowOnValues;
        $element[$radiosDrupalName] = $aRadios;
        $element['question-text'] = array('#markup' => "\n".'<div class="question">'.$question_tx.'</div>');
        $element[$commentDrupalName] = $aComment;
        $element[] = array('#markup' => "\n".'</div>');
        return $element;
    }
    
    /**
     * Get all the questions as an array of arrays.
     * There are only three types of questions, progressive filtering ...
     * 1. No filter
     * 2. Filtered on modality
     * 3. Filtered down to one protocol (a protocol applies to only one modality already)
     */
    function getAllQuestions($sChecklistType='SC',$modality_abbr='',$protocol_shortname='')
    {
        $aQuestionRef = array();
        $result = db_select('raptor_checklist_question','q')
                ->fields('q')
                ->orderBy('relative_position')
                ->condition('q.type_cd',$sChecklistType,'=')
                ->condition('modality_abbr','','=')
                ->condition('protocol_shortname','','=')
                ->execute();
        while($record = $result->fetchAssoc())
        {
            $shortname = $record['question_shortname'];
            $aQuestionRef[$shortname] = $record;
            $aQuestionRef[$shortname]['subtype'] = 'core';
        }
        if($modality_abbr > '')
        {
            //Now grab any questions specific to this modality.
            $result = db_select('raptor_checklist_question','q')
                    ->fields('q')
                    ->orderBy('relative_position')
                    ->condition('q.type_cd',$sChecklistType,'=')
                    ->condition('modality_abbr',$modality_abbr,'=')
                    ->condition('protocol_shortname','','=')
                    ->execute();
            while($record = $result->fetchAssoc())
            {
                $shortname = $record['question_shortname'];
                $aQuestionRef[$shortname] = $record;
                $aQuestionRef[$shortname]['subtype'] = 'modality';
            }
        }
        if($protocol_shortname > '')
        {
            //Now grab any questions specific to this protocol.
            $result = db_select('raptor_checklist_question','q')
                    ->fields('q')
                    ->orderBy('relative_position')
                    ->condition('q.type_cd',$sChecklistType,'=')
                    ->condition('modality_abbr','','=')
                    ->condition('protocol_shortname',$protocol_shortname,'=')
                    ->execute();
            while($record = $result->fetchAssoc())
            {
                $shortname = $record['question_shortname'];
                $aQuestionRef[$shortname] = $record;
                $aQuestionRef[$shortname]['subtype'] = 'protocol';
            }
        }
        return $aQuestionRef;
    }
    
    /**
     * The user has to respond to the checklist questions provided by this function.
     * @param type $form_state
     * @param type $disabled
     * @param type $myvalues
     * @param type $sSectionTitle
     * @param type $sChecklistType
     * @param type $modality_abbr
     * @param type $protocol_shortname
     * @return element for a renderable form array
     */
    function getPageChecklistArea(&$form_state, $disabled, $myvalues, $sSectionTitle, $sChecklistType, $modality_abbr, $protocol_shortname)
    {

        $root = array(
            '#type'     => 'fieldset',
            '#title'    => t($sSectionTitle),
            '#attributes' => array(
                'class' => array(
                    'checklist-dataentry-area'
                )
             ),
            '#disabled' => $disabled,
        );
        $root[] = array('#markup' => '<div class="safety-checklist">');
        
        //die('LOOK all answers>>>'.print_r($myvalues['questions'],TRUE));      

        //Grab all the relevant questions.
        $aQuestionRef = $this->getAllQuestions($sChecklistType,$modality_abbr,$protocol_shortname);
        //First grab all the checklist questions already answered by other users.
        if(isset($myvalues['questions']['otheruser']))
        {
            $aAnsweredByOtherUsers = $myvalues['questions']['otheruser'];
            $otherusersarea = array();
            foreach($aAnsweredByOtherUsers as $nUID=>$aFromOneUser)
            {
                if(is_array($aFromOneUser))
                {
                    //die('Look now>>>'.$nUID.'>>>'.print_r($aFromOneUser,TRUE));
                    $oOtherUser = new \raptor\UserInfo($nUID,FALSE);
                    $username = $oOtherUser->getFullName();
                    $otherusersarea[$nUID] = array(
                        '#type'     => 'fieldset',
                        '#title'    => t('Answers from '. $username),
                        '#attributes' => array(
                            'class' => array(
                                'otheruser-safety-checklist-answers-area'
                            )
                         ),
                        '#disabled' => TRUE,
                    );
                    $element = array('#markup' => '<ol>' );
                    $otherusersarea[$nUID][] = $element;
                    $questionnumber = 0;
                    foreach($aFromOneUser as $sShortName=>$aOneQuestionAnswer)
                    {
                        $details = $aQuestionRef[$sShortName];
                        $question_tx = $details['question_tx'];
                        $comment_prompt_tx = $details['comment_prompt_tx'];
                        $response = $aOneQuestionAnswer['response'];
                        $comment = $aOneQuestionAnswer['comment'];
                        $element = array('#markup' => '<li>'.$question_tx.'<p>'.$response.'</p>');
                        $otherusersarea[$nUID][] = $element;
                        if(trim($comment) > '')
                        {
                            $element = array('#markup' => '<p class="comment-prompt">'.$comment_prompt_tx.'</p><p>'.$comment.'</p>');
                            $otherusersarea[$nUID][] = $element;
                        }
                    }
                    $element = array('#markup' => '</ol>' );
                    $otherusersarea[$nUID][] = $element;
                }
            }
            $root['other_answers'] = &$otherusersarea;
        }

        //Now show the content for this user.
        if(!$disabled || (isset($myvalues['questions']['thisuser']) && is_array($myvalues['questions']['thisuser'])))
        {
            $questionnumber = 0;
            foreach($aQuestionRef as $aOneQuestion)
            {
                $questionnumber++;
                if($aOneQuestion['subtype'] == 'core')
                {
                    $title = 'Core Question '.$questionnumber;
                }
                if($aOneQuestion['subtype'] == 'modality')
                {
                    $title = 'Modality Specific Question '.$questionnumber;
                }
                if($aOneQuestion['subtype'] == 'protocol')
                {
                    $title = 'Protocol Specific Question '.$questionnumber;
                }
                $shortname = $aOneQuestion['question_shortname'];
                $element = $this->getOneChecklistQuestion($myvalues,$title,$questionnumber,$aOneQuestion,$disabled);
                $root['questions']['thisuser'][$shortname] = $element;
            }
            $root['questions']['#tree'] = TRUE;
            $root[] = array('#markup' => '</div><!-- end of safety checklist for modality=['.$modality_abbr.'] of protocol=['.$protocol_shortname.'] -->');
        }
        
        return $root;
    }    
    
    function getPageActionButtonsArea(&$form_state, $disabled, $myvalues, $has_uncommitted_data=FALSE,$commited_dt=NULL)
    {
        $oContext = \raptor\Context::getInstance();
        $userinfo = $oContext->getUserInfo();
        $userprivs = $userinfo->getSystemPrivileges();
        $nSiteID = $this->m_oContext->getSiteID();
        $nIEN = $myvalues['tid'];
        $sCWFS = $this->getCurrentWorkflowState($nSiteID, $nIEN);
        
        $acknowledgeTip = 'Acknowledge the presented protocol so the exam can begin.';
        $examcompletionTip = 'Save all current settings and mark the examination as completed.';
        $interpretationTip = 'Save interpretation notes.';
        $qaTip = 'Save QA notes.';
        if($oContext->hasPersonalBatchStack())
        {
            $sRequestApproveTip = 'Save this order as ready for review and continue with next available personal batch selection.';
            $releaseTip = 'Release this order without saving changes and continue with next available personal batch selection.';
            $reserveTip = 'Assign this order to yourself with current edits saved and continue with the next available personal batch selection.';
            $collaborateTip = 'Assign this order a specialist with current edits saved and continue with the next available personal batch selection.';
            $approveTip = 'Save this order as approved and continue with the next available personal batch selection.';
            $suspendTip = 'Suspend this order without saving edits and continue with the next available personal batch selection.';
            $cancelOrderTip = 'Cancel this order in VISTA and continue with the next available personal batch selection.';
            $sUnsuspendTip = 'Restore this order back to the worklist and continue with next available personal batch selection.';
        } else {
            $sRequestApproveTip = 'Save this order as ready for review and return to the worklist.';
            $releaseTip = 'Release this order without saving changes and return to the worklist.';
            $reserveTip = 'Assign this order to yourself with current edits saved and return to the worklist.';
            $collaborateTip = 'Assign this order to a specialist with current edits saved and return to the worklist.';
            $approveTip = 'Save this order as approved and return to the worklist.';
            $cancelOrderTip = 'Cancel this order in VISTA and return to the worklist.';
            $suspendTip = 'Suspend this order without saving changes and return to the worklist.';
            $sUnsuspendTip = 'Restore this order back to the worklist.';
        }
        $feedback = NULL;
        
        $form['page_action_buttons_area'] = array(
            '#type' => 'container',
            '#attributes' => array('class'=>array('form-action')),
        );
        if($sCWFS == 'AP') 
        {
            $form['page_action_buttons_area']['acknowledge_button'] = array('#type' => 'submit'
                , '#value' => t('Acknowledge Protocol')
                , '#attributes' => array('title' => $acknowledgeTip)
                //, '#validate' => array('raptor_datalayer_protocolinfo_form_builder_customvalidate')
                //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                );
        } else if($sCWFS == 'PA') {
            $form['page_action_buttons_area']['examcompleted_button'] = array('#type' => 'submit'
                , '#value' => t('Exam Completed')
                , '#attributes' => array('title' => $examcompletionTip)
                //, '#validate' => array('raptor_datalayer_protocolinfo_form_builder_customvalidate')
                //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                );
        } else if($sCWFS == 'EC') {
            $form['page_action_buttons_area']['interpret_button'] = array('#type' => 'submit'
                , '#value' => t('Interpretation Complete')
                , '#attributes' => array('title' => $interpretationTip)
                //, '#validate' => array('raptor_datalayer_protocolinfo_form_builder_customvalidate')
                //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                );
            if($has_uncommitted_data)
            {
                $form['page_action_buttons_area']['interpret_button_and_commit'] = array('#type' => 'submit'
                    , '#value' => t('Interpretation Complete and Commit Details to Vista')
                    , '#attributes' => array('title' => $interpretationTip)
                    //, '#validate' => array('raptor_datalayer_protocolinfo_form_builder_customvalidate')
                    //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                    , '#disabled' => FALSE,  //Not ready as of 20140810
                    );
            } else {
                $feedback = 'All procedure data has been committed to Vista';
                if($commited_dt != NULL)
                {
                    $feedback .= ' as of ' . $commited_dt;
                }
            }
        } else if($sCWFS == 'QA') {
            $form['page_action_buttons_area']['qa_button'] = array('#type' => 'submit'
                , '#value' => t('QA Complete')
                , '#attributes' => array('title' => $qaTip)
                //, '#validate' => array('raptor_datalayer_protocolinfo_form_builder_customvalidate')
                //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                );
            if($has_uncommitted_data)
            {
                $form['page_action_buttons_area']['qa_button_and_commit'] = array('#type' => 'submit'
                    , '#value' => t('QA Complete and Commit Details to Vista')
                    , '#attributes' => array('title' => $qaTip)
                    //, '#validate' => array('raptor_datalayer_protocolinfo_form_builder_customvalidate')
                    //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                    , '#disabled' => FALSE,  //Not ready as of 20140810
                    );
            } else {
                $feedback = 'All procedure data has been committed to Vista';
                if($commited_dt != NULL)
                {
                    $feedback .= ' as of ' . $commited_dt;
                }
            }
        }
        if($sCWFS == 'AC' || $sCWFS == 'CO' || $sCWFS == 'AP' || $sCWFS == 'RV')
        {
            if($userprivs['PWI1'] == 1)
            {
                if($sCWFS == 'AC' || $sCWFS == 'CO' || $sCWFS == 'RV')
                {
                    if($userprivs['APWI1'] == 1)
                    {
                        $form['page_action_buttons_area']['approve_button'] = array('#type' => 'submit'
                            , '#value' => t('Approve')
                            , '#attributes' => array('title' => $approveTip)
                            //, '#validate' => array('raptor_datalayer_protocolinfo_form_builder_customvalidate')
                            //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                            );
                    } else {
                        $form['page_action_buttons_area']['request_approve_button'] = array('#type' => 'submit'
                            , '#value' => t('Request Approval')
                            , '#attributes' => array('title' => $sRequestApproveTip)
                            //, '#validate' => array('raptor_datalayer_protocolinfo_form_builder_customvalidate')
                            //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                            );
                    }
                    $form['page_action_buttons_area']['collaborate_button'] = array('#markup' => '<input id="raptor-protocol-collaborate" type="button" value="Collaborate" title="'.$collaborateTip.'">');
                }
            }
        }
        
        global $base_url;
        $form['page_action_buttons_area']['release_button'] = array('#type' => 'button'
            , '#value' => t('Release back to Worklist without Saving')
            , '#attributes' 
              => array('onclick' 
                 => 'javascript:window.location.href="/drupal/protocol?pbatch=CONTINUE&releasedticket=TRUE";return false;'
                    ,'title' => $releaseTip)
            //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
            );
        /*
        $form['page_action_buttons_area']['release_button'] = array(
            '#type' => 'link'
          , '#title' => t('Release back to Worklist without Saving')
          , '#href' => $base_url.'/protocol?pbatch=CONTINUE&releasedticket=TRUE'
          , '#attributes' => array('title' => $releaseTip,)
        );
         */
        $form['page_action_buttons_area']['release_button']['#attributes']['class'][] = 'action-button';
        
        if($sCWFS == 'AC' || $sCWFS == 'CO' || $sCWFS == 'RV')
        {
            if($sCWFS == 'CO')
            {
                $query = db_select('raptor_ticket_collaboration', 'n');
                $query->join('raptor_user_profile','u','n.collaborator_uid = u.uid');
                $query->fields('n',array('collaborator_uid','requested_dt','requester_notes_tx','active_yn'));
                $query->fields('u',array('username', 'usernametitle', 'firstname', 'lastname', 'suffix'));
                $query->condition('n.siteid',$nSiteID,'=');
                $query->condition('n.IEN',$nIEN,'=');
                $query->condition('n.active_yn',1,'=');
                $result = $query->execute();
                $record = $result->fetchAssoc();
                if($record != NULL)
                {
                    $fullname = trim($record['usernametitle'] . ' ' . $record['firstname'] . ' ' . $record['lastname'] . ' ' . $record['suffix']);
                    $assignmentBlurb = 'already assigned to '.$fullname;
                } else {
                    //This should not happen but if it does leave a clue
                    $errMsg = ('Did NOT find name of user assigned for collaboration on ticket '.$nSiteID.'-'.$nIEN);
                    error_log($errMsg);
                    drupal_set_message($errMsg,'error');
                    $assignmentBlurb = 'already assigned';
                }
                
                $form['page_action_buttons_area']['reserve_button'] = array('#type' => 'submit'
                    , '#value' => t('Reserve ('.$assignmentBlurb.')')
                    , '#attributes' => array('title' => $reserveTip)
                    //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                    );
            } else {
                $form['page_action_buttons_area']['reserve_button'] = array('#type' => 'submit'
                    , '#value' => t('Reserve')
                    , '#attributes' => array('title' => $reserveTip)
                    //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                    );
            }
        }
        if($userprivs['SUWI1'] == 1 && ($sCWFS != 'EC' && $sCWFS != 'QA'))
        {
            if($sCWFS == 'IA')
            {
                $form['page_action_buttons_area']['unsuspend_button'] = array('#type' => 'submit'
                    , '#value' => t('Unsuspend')
                    , '#attributes' => array('title' => $sUnsuspendTip)
                    //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                    );
            } else {
                /*
                $form['page_action_buttons_area']['suspend_button'] = array('#type' => 'submit'
                    , '#value' => t('Suspend')
                    , '#attributes' => array('title' => $suspendTip)
                    //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                    );
                 */
                $form['page_action_buttons_area']['cancelorder_button'] = array('#type' => 'submit'
                    , '#value' => t('Cancel Order')
                    , '#attributes' => array('title' => $cancelOrderTip)
                    //, '#submit' => array('raptor_datalayer_protocolinfo_form_builder_customsubmit')
                    );
            }
        }
        if($feedback != NULL)
        {
            $form['page_action_buttons_area']['feedback'] = array(
                '#markup' => ' <span class="action-area-feedback">'.t($feedback).'</span>'
                );
        }

        $form['page_action_buttons_area']['bottom_filler'] = array(
            '#markup' => '<br><br><br><!-- Bottom gap -->',
        );
        
        return $form;
    }

    public function getOrderPhraseKeywords($phrase)
    {
        $ignorelist = array('CT','MR','FL','NM','W/O','W','W&WO','W/WO','INCLUDE','CONT'); //Terms to ignore in the order name for keyword matching purposes
        return $this->getKeywords($phrase, $ignorelist);
    }
    
    public function getKeywords($phrase, $ignorelist=NULL)
    {
        $keywords = explode(' ', $phrase);
        if($ignorelist !== NULL)
        {
            foreach($keywords as $kw)
            {
                $ignore = FALSE;
                foreach($ignorelist as $ilw)
                {
                    if($kw == $ilw)
                    {
                        $ignore = TRUE;
                        break;
                    }
                }
                if(!$ignore)
                {
                    $keep[] = $kw;
                }
            }
            $keywords = $keep;
        }
        return $keywords;
    }
    
    /**
     * @return int number of words that matched between the lists
     */
    public function countMatchingWords($list1,$list2)
    {
        $count = 0;
        if(is_array($list1) && is_array($list2))
        {
            foreach($list1 as $word1)
            {
                if(trim($word1) > '')
                {
                    foreach($list2 as $word2)
                    {
                        if($word1 == $word2)
                        {
                            $count++;
                        }
                    }
                }
            }
        }
        return $count;
    }
    
    /**
     * Get the list of protocol choices.
     */
    private function getProtocolChoices($procName=NULL
            ,$sFirstElementText=''
            ,$clues=NULL)
    {
        
        //drupal_set_message('CLUES='.print_r($clues,TRUE));
        
        //TODO CACHE THESE THINGS FOR BETTER PERFORMANCE!!!!!!!!!
        $result = db_select('raptor_protocol_lib','p')
                ->fields('p')
                ->orderBy('modality_abbr', 'ASC')
                ->orderBy('name', 'ASC')
                ->execute();
        $aShortList = array();
        $aCombinedList = array();
        $ignorelist = array('CT','MR','FL','NM','W/O','W','INCLUDE', 'CONT'); //Terms to ignore in the order name for keyword matching purposes
        while($record = $result->fetchAssoc()) 
        {
            $categoryname = trim($record['modality_abbr']).' List';
            if($categoryname == ' List')
            {
                //Put it on the shortlist
                $categoryname = 'Short List';
            }
            $oC = new \raptor\FormControlChoiceItem(
                    $record['name']
                    ,$record['protocol_shortname']
                    ,$categoryname
                    ,FALSE);
            $aCombinedList[] = $oC;

            if($categoryname != 'Short List')
            {
                //Does this also belong on the shortlist?
                $matchscore = 0;
                if($record['modality_abbr'] == $clues['modality_abbr'])
                {
                    $matchscore++;
                }
                if($matchscore > 0 || trim($clues['modality_abbr']) == '' )
                {
                    if($clues['contrast'] === NULL || $record['contrast_yn'] === NULL 
                            || ($record['contrast_yn'] == 1 && $clues['contrast'] === TRUE)
                            || ($record['contrast_yn'] == 0 && $clues['contrast'] === FALSE))
                    {
                        if(is_array($clues['cpt_codes']))
                        {
                            //TODO -- give score updates for CPT code matches
                        }
                        $tempkw = $this->getOrderPhraseKeywords(strtoupper($record['name']));
                        $kwres = db_select('raptor_protocol_keywords','p')
                            ->fields('p')
                                ->condition('protocol_shortname',$record['protocol_shortname'],'=')
                            ->orderBy('weightgroup', 'ASC')
                            ->execute();
                        $kgroups = array();
                        while($kwrec = $kwres->fetchAssoc()) 
                        {
                            $kw = trim($kwrec['keyword']);
                            $wg = trim($kwrec['weightgroup']);
                            $kgroups[$wg][] = $kw;
                        }
                        foreach($kgroups as $wg=>$kwg)
                        {
                            $matches = $this->countMatchingWords($kwg, $clues['keywords']);
                            $matchscore += (5 - $wg) * $matches;
                        }
                        $matches = $this->countMatchingWords($tempkw, $clues['keywords']);
                        if($matches > 0)
                        {
                            $matchscore += ($matches + 1);    //MUST add one more!!!
                        }
                        if($matchscore > 1)  //Must have score greater than 1!
                        {
                            //Good enough on contrast check
                            $categoryname = 'Short List';
                            $oC = new \raptor\FormControlChoiceItem(
                                    $record['name']
                                    ,$record['protocol_shortname']
                                    ,$categoryname
                                    ,FALSE);
                            $oC->nScore = $matchscore;
                            $aShortList[$matchscore][] = $oC;
        //drupal_set_message('SCORE='.$matchscore.' GOT='.print_r($oC,TRUE));
                        }
                    }
                }
            }
        }
        $aFinalList = array();
        $oC = new \raptor\FormControlChoiceItem(
                $sFirstElementText
                ,NULL
                ,NULL
                ,FALSE);
        $aFinalList[] = $oC;
        ksort($aShortList);
        foreach($aShortList as $k=>$list)
        {
            foreach($list as $oC)
            {
                $aFinalList[] = $oC;
            }
        }
        foreach($aCombinedList as $oC)
        {
            $aFinalList[] = $oC;
        }

        return $aFinalList;
    }

    /**
     * Get the the protocol selection section element
     */
    function getProtocolSelectionElement(&$form_state, $disabled
            , $myvalues,$bFindMatch=TRUE
            , $sBaseName='protocol1'
            , $sDescription=NULL
            , $bUseAjax=FALSE, $bRequireValue=FALSE
            , $clues=NULL)
    {

        $oPPU = new ProtocolPageUtils();
        if($sBaseName == 'protocol1')
        {
            $title = t('Protocol Name');
        } else {
            $title = t('Secondary Protocol Name');
        }
        $root = array(
            '#type'     => 'fieldset',
            '#title'    => $title,
            '#attributes' => array(
                'class' => array(
                    'data-entry1-area'
                )
             ),
            '#disabled' => $disabled,
        );

        if($bFindMatch)
        {
            $choices  = $this->getProtocolChoices($myvalues['procName'],"- Select -", $clues);
        } else {
            $choices  = $this->getProtocolChoices("");
        }
        //drupal_set_message('>>>choices>>>'.print_r($choices,TRUE));
        $element  = array(
            '#type'        => 'select',
            '#description' => $sDescription,
            '#attributes' => array(
                'class' => array(
                    'select2'
                )
             ),
            '#select2' => array(),
            );
        if (isset($myvalues[$sBaseName.'_nm']))
        {
            if ($disabled)
            {
                $element['#value']         = $myvalues[$sBaseName.'_nm'];
            }
            else
            {
                $element['#default_value'] = $myvalues[$sBaseName.'_nm'];
                //$element['#required']      = $bRequireValue;
            }
        }
        
        $element = $oPPU->getFAPI_select_options($element, $choices);
        if($bUseAjax)
        {
            $element['#ajax'] = array(
                'callback' => 'raptor_fetch_protocol_defaults',
                //'wrapper' => 'protocol-template-data',    //Using other commands in the callback instead
                //'method' => 'replace'
            );
        }

        $root[$sBaseName.'_nm']                             = $element;
        $form['main_fieldset_left'][$sBaseName.'_fieldset'] = &$root;

        return $form;
    }
    
    /**
     * Create subsection element based on vector
     */
    function getVectorBasedSubSectionMarkup(&$root,$vector,$myvalues,$section_name,$bShowCustom,$aChoices,$disabled)
    {
        $sListRootName = $section_name . '_'.$vector.'_';
        //$sListboxName  = $sListRootName.'id';
        $value_itemname = $sListRootName.'customtx';
        $aStatesEntry = NULL;
        $sInlineName = 'inline_'.$vector;
        $root[$section_name.'_fieldset_col1'][$sListRootName . '_inputmode'] = array(
            '#type'  => 'hidden',   //Needed at database update time to know what control to read!
        );
        $root[$section_name.'_fieldset_col2'][$sInlineName] = array(
            '#type'       => 'fieldset',
            '#attributes' => array('class' => array('container-inline')),
            '#disabled' => $disabled,
        );
        if(!isset($myvalues[$value_itemname]))
        {
            $mydefault_value = NULL;
        } else {
            $mydefault_value = $myvalues[$value_itemname];
        }
        $element = FormHelper::createCustomSelectPanel($section_name, $sListRootName, $aChoices, $disabled
                        , $aStatesEntry
                        , $myvalues
                        , $bShowCustom, $mydefault_value);
        $root[$section_name.'_fieldset_col2'][$sInlineName]['panel'] =  $element;     
    }
    
    /**
     * Return panel with radios and custom value selection controls
     * @param type $supportEditMode TODO REMOVE THIS 
     * @param array $aCustomOverride 'oral=>'customtx' and 'iv'=>'customtx'
     * @return associative array of controls
     */
    function getSectionCheckboxType($section_name, $titleoverride
            , $aEntericChoices, $aIVChoices, &$form_state, $disabled, $myvalues
            , $containerstates, $supportEditMode=TRUE
            , $aCustomOverride=NULL
            , $shownow=TRUE
            , $req_ack=FALSE)
    {
        if($aCustomOverride == NULL)
        {
            $aCustomOverride = array();
        }
        $bShowCustomEnteric = (isset($aCustomOverride['enteric']));
        $bShowCustomIV = (isset($aCustomOverride['iv']));
        $bLockedReadonly = $disabled && !$supportEditMode;

        $checkboxname= $section_name . '_cd';
        
        $root = array(
            '#type'     => 'fieldset',
            '#title'    => t($titleoverride),
            '#attributes' => array(
                'class' => array(
                    'data-entry1-area'
                )
             ),
            '#disabled' => $disabled,
        );
        if($containerstates != null)
        {
            //$root['#states'] = $containerstates;
        }
        //Always declare as collapsible otherwise Drupal framework does NOT load javascript support
        $root['#collapsible'] = TRUE;
        if(!$shownow)
        {
            $root['#collapsed'] = TRUE;
            if($disabled)
            {
                //Never show it.
                $root['#attributes']['style'] = array('display:none');
            }
        } else {
            $root['#collapsible'] = TRUE;
            $root['#collapsed'] = FALSE;
        }

        $root[$section_name.'_fieldset_col1'] = array(
            '#type' => 'fieldset',
        );
        $root[$section_name.'_fieldset_col2'] = array(
            '#type' => 'fieldset',
        );
        $root[$section_name.'_fieldset_col3'] = array(
            '#type' => 'fieldset',
        );
        $root[$section_name.'_fieldset_row2'] = array(
            '#type' => 'fieldset',
        );
        $options                              = array(
            'none' => t('None'),
            'enteric' => t('Enteric'),
            'iv'   => t('IV')
        );
        $default_values = NULL;
        if(is_array($myvalues[$checkboxname]) 
                && count($myvalues[$checkboxname]) > 0)
        {
            $default_values = $myvalues[$checkboxname];
        } else {
            //$default_values = array('none'=>'none');
        }
        $root[$section_name.'_fieldset_col1'][$checkboxname] = array(
            '#type'          => 'checkboxes',
            '#options'       => $options,
            '#attributes' => $bLockedReadonly ? array() : array('onchange' 
                => 'notDefaultValuesInSectionAndSetCheckboxes("'.$section_name.'",this)'),
            '#disabled' => $disabled,
        );
        if ($default_values !== NULL)
        {
            $root[$section_name.'_fieldset_col1'][$checkboxname]['#default_value'] = $default_values;
        }
        $root[$section_name.'_fieldset_col2'][$section_name . '_markup1'] = array(
            '#markup' => '<div class="v-spacer-select">&nbsp;</div>',
        );

        //Create the two subsections.
        $this->getVectorBasedSubSectionMarkup($root,'enteric',$myvalues,$section_name,$bShowCustomEnteric,$aEntericChoices,$disabled);
        $this->getVectorBasedSubSectionMarkup($root,'iv',$myvalues,$section_name,$bShowCustomIV,$aIVChoices,$disabled);
        
        //Create the acknowledgement control
        $defaultvalue = isset($myvalues['DefaultValues'][$section_name]) ? $myvalues['DefaultValues'][$section_name] : NULL;
        $root[$section_name.'_fieldset_row2']['showconditionally'] 
                = $this->getDefaultValueControls($section_name, $disabled, $defaultvalue, $req_ack);
        //Always show this in each section that can have default values!
        if(!$disabled)
        {
            $root[$section_name.'_fieldset_col3']['reset_'.$section_name] = array(
                '#markup' => "\n"
                . '<div class="reset-values-button-container" name="reset-section-values">'
                . '<a href="javascript:setDefaultValuesInSection('."'".$section_name."',getTemplateDataJSON()".')" title="The default template values will be restored">'
                . 'RESET'
                . '</a>'
                . '</div>', 
                '#disabled' => $disabled,
            );
        }

        $form['main_fieldset_left'][$section_name . '_fieldset'] = &$root;
        return $form;
    }    

    
    function getYesNoRadioTypeSection($section_name, $titleoverride
            , $disabled
            , $myvalues
            , $containerstates
            , $supportEditMode=TRUE)
    {

        $radioname = $section_name.'_cd';
        if($titleoverride == NULL)
        {
            $titleoverride = $section_name;
        }
        $root = array(
            '#type'     => 'fieldset',
            '#title'    => t($titleoverride),
            '#attributes' => array(
                'class' => array(
                    'data-entry1-area'
                )
             ),
            '#disabled' => $disabled,
        );
        if($containerstates != null)
        {
            $root['#states'] = $containerstates;
        }

        $options          = array(
            'unknown' => t('Unknown'),
            'no'      => t('No'),
            'yes'     => t('Yes'),
        );
        $root[$radioname] = array(
            '#type'    => 'radios',
            '#attributes' => array(
                'class' => array('container-inline'),
                ),
            '#options' => $options,
        );
        if(isset($myvalues[$radioname]))
        {
            $root[$radioname]['#default_value'] = $myvalues[$radioname];
        }

        $form['main_fieldset_left'][$section_name . '_fieldset'] = &$root;
        return $form;
    }
    
    
    
    /**
     * Return panel with radios and custom value selection controls
     * @param type $supportEditMode TODO REMOVE THIS 
     * @param array $aCustomOverride 'oral=>'customtx' and 'iv'=>'customtx'
     * @return associative array of controls
     */
    function getSectionRadioType($section_name, $titleoverride
            , $aOralChoices
            , $aIVChoices, &$form_state, $disabled, $myvalues
            , $containerstates
            , $supportEditMode=TRUE, $aCustomOverride=NULL
            , $shownow=TRUE
            , $req_ack=FALSE)
    {
        if($aCustomOverride == NULL)
        {
            $aCustomOverride = array();
        }
        $bShowCustomOral = (isset($aCustomOverride['oral']));
        $bShowCustomIV = (isset($aCustomOverride['iv']));
        $bLockedReadonly = $disabled && !$supportEditMode;
        
        $root = array(
            '#type'     => 'fieldset',
            '#title'    => t($titleoverride),
            '#attributes' => array(
                'class' => array(
                    'data-entry1-area'
                )
             ),
            '#disabled' => $disabled,
        );
        if($containerstates != null)
        {
            //TODO re-enable this>>>>> $root['#states'] = $containerstates;
        }
        //Always declare as collapsible otherwise Drupal framework does NOT load javascript support
        $root['#collapsible'] = TRUE;
        if(!$shownow)
        {
            $root['#collapsed'] = TRUE;
            if($disabled)
            {
                //Never show it.
                $root['#attributes']['style'] = array('display:none');
            }
        } else {
            $root['#collapsible'] = TRUE;
            $root['#collapsed'] = FALSE;
        }

        $col1fieldsetname = $section_name.'_fieldset_col1';
        $col2fieldsetname = $section_name.'_fieldset_col2';
        $col3fieldsetname = $section_name.'_fieldset_col3';
        $row2fieldsetname = $section_name.'_fieldset_row2';
        $markup1name = $section_name . '_markup1';
        $root[$col1fieldsetname] = array(
            '#type' => 'fieldset',
        );
        $root[$col2fieldsetname] = array(
            '#type' => 'fieldset',
        );
        $root[$col3fieldsetname] = array(
            '#type' => 'fieldset',
        );
        $root[$row2fieldsetname] = array(
            '#type' => 'fieldset',
        );
        $radio_nm = $section_name . '_radio_cd';
        if(!isset($myvalues[$radio_nm]) || $myvalues[$radio_nm] == '')
        {
            //Hardwired default value.
            $defaultoptionvalue = NULL; //'none';
        } else {
            $defaultoptionvalue = $myvalues[$radio_nm];
        }
        $options                 = array(
            'none' => t('None'),
            'oral' => t('Oral'),
            'iv'   => t('IV'),
        );
        $root[$col1fieldsetname][$radio_nm] = array(
            '#type'    => 'radios',
            '#options' => $options,
            '#attributes' => $bLockedReadonly ? array() : array('onchange' => 'notDefaultValuesInSection("'.$section_name.'")'),
            '#disabled' => $disabled,
        );
        if($defaultoptionvalue !== NULL)
        {
            $root[$col1fieldsetname][$radio_nm]['#default_value'] = $defaultoptionvalue;
        }
        $root[$col2fieldsetname][$markup1name] = array(
            '#markup' => '<div class="v-spacer-select">&nbsp;</div>',
        );

        //Create the two subsections.
        $this->getVectorBasedSubSectionMarkup($root,'oral',$myvalues,$section_name,$bShowCustomOral,$aOralChoices,$disabled);
        $this->getVectorBasedSubSectionMarkup($root,'iv',$myvalues,$section_name,$bShowCustomIV,$aIVChoices,$disabled);
        
        
        //Create the acknowledgement control
        $defaultoptionvalue = isset($myvalues['DefaultValues'][$section_name]) ? $myvalues['DefaultValues'][$section_name] : NULL;
        $root[$row2fieldsetname]['showconditionally'] 
                = $this->getDefaultValueControls($section_name, $disabled, $defaultoptionvalue, $req_ack);
        //Always show this in each section that can have default values!
        if(!$disabled)
        {
            $root[$col3fieldsetname]['reset_'.$section_name] = array(
                '#markup' => "\n".'<div class="reset-values-button-container" name="reset-section-values">'
                . '<a href="javascript:setDefaultValuesInSection('."'".$section_name."',getTemplateDataJSON()".')" title="The default template values will be restored">'
                . 'RESET'
                . '</a>'
                . '</div>', 
                '#disabled' => $disabled,
            );
        }

        $form['main_fieldset_left'][$section_name . '_fieldset'] = &$root;
        return $form;
    }

    function getOverallSectionRadioType(&$form_state
            , $section_name, $titleoverride
            , $disabled
            , $myvalues
            , $containerstates=NULL
            , $supportEditMode=TRUE
            , $shownow=TRUE
            , $req_ack=FALSE)
    {
        if($titleoverride == NULL)
        {
            $titleoverride = $section_name; 
        }
        $radioname = $section_name.'_radio_cd';
        $oChoices      = new \raptor\raptor_datalayer_Choices();    
        $bFoundInList = FALSE;  //Initialize
        $aControlOverrides = array();   //Initialize
        
        $oral_tx = isset($myvalues[$section_name.'_oral_customtx']) ? $myvalues[$section_name.'_oral_customtx'] : '';
        $aOralChoices  = $oChoices->getOralHydrationData($oral_tx, $bFoundInList);
        if($oral_tx > '')
        {
            $myvalues[$radioname] = 'oral';
            if(!$bFoundInList)
            {
                $aControlOverrides['oral'] = $oral_tx;
            }
        }
        
        $iv_tx = isset($myvalues[$section_name.'_iv_customtx']) ? $myvalues[$section_name.'_iv_customtx'] : '';
        $aIVChoices    = $oChoices->getIVHydrationData($iv_tx, $bFoundInList);
        if($iv_tx > '')
        {
            $myvalues[$radioname] = 'iv';
            if(!$bFoundInList)
            {
                $aControlOverrides['iv'] = $iv_tx;
            }
        }
        return $this->getSectionRadioType($section_name, $titleoverride
                , $aOralChoices, $aIVChoices, $form_state
                , $disabled, $myvalues, $containerstates
                , $supportEditMode, $aControlOverrides
                , $shownow
                , $req_ack);
    }
    
    /**
     * Return TRUE if we have hydration data.
     */
    function hasHydrationValues($myvalues)
    {
        $b1 = isset($myvalues['hydration_oral_customtx']) && $myvalues['hydration_oral_customtx'] > '';
        $b2 = isset($myvalues['hydration_iv_customtx']) && $myvalues['hydration_iv_customtx'] > '';
        return $b1 || $b2;
    }
    
    /**
     * Return TRUE if we have sedation data.
     */
    function hasSedationValues($myvalues)
    {
        $b1 = isset($myvalues['sedation_oral_customtx']) && $myvalues['sedation_oral_customtx'] > '';
        $b2 = isset($myvalues['sedation_iv_customtx']) && $myvalues['sedation_iv_customtx'] > '';
        return $b1 || $b2;
    }

    /**
     * Return TRUE if we have contrast data.
     */
    function hasContrastValues($myvalues, $form_state=NULL)
    {
        
        $sectionname = 'contrast';
        $b1 = isset($myvalues[$sectionname.'_enteric_customtx']) && $myvalues[$sectionname.'_enteric_customtx'] > '';
        $b2 = isset($myvalues[$sectionname.'_iv_customtx']) && $myvalues[$sectionname.'_iv_customtx'] > '';
        $foundvalues = $b1 || $b2;
        if(!$foundvalues && $form_state != NULL)
        {
            $b1 = isset($form_state['input'][$sectionname.'_enteric_customtx']) && $form_state['input'][$sectionname.'_enteric_customtx'] > '';
            $b2 = isset($form_state['input'][$sectionname.'_iv_customtx']) && $form_state['input'][$sectionname.'_iv_customtx'] > '';
            $foundvalues = $b1 || $b2;
        }
        
        //die('LOOK>>>>'.$sectionname.'='.$foundvalues.'>>>'.print_r($form_state['input'],TRUE));
        
        return $foundvalues;
    }

    /**
     * Return TRUE if we have radioisotope data.
     */
    function hasRadioisotopeValues($myvalues, $form_state=NULL)
    {
        $sectionname = 'radioisotope';
        $b1 = isset($myvalues[$sectionname.'_enteric_customtx']) && $myvalues[$sectionname.'_enteric_customtx'] > '';
        $b2 = isset($myvalues[$sectionname.'_iv_customtx']) && $myvalues[$sectionname.'_iv_customtx'] > '';
        $foundvalues = $b1 || $b2;
        if(!$foundvalues && $form_state != NULL)
        {
            $b1 = isset($form_state['input'][$sectionname.'_enteric_customtx']) && $form_state['input'][$sectionname.'_enteric_customtx'] > '';
            $b2 = isset($form_state['input'][$sectionname.'_iv_customtx']) && $form_state['input'][$sectionname.'_iv_customtx'] > '';
            $foundvalues = $b1 || $b2;
        }
        return $foundvalues;
    }
    
    function getOverallSectionCheckboxType(&$form_state
            , $section_name, $titleoverride
            , $disabled
            , $myvalues
            , $containerstates=NULL
            , $supportEditMode=TRUE
            , $shownow=TRUE
            , $req_ack=FALSE )
    {
        if($titleoverride == NULL)
        {
            $titleoverride = $section_name; 
        }
        $oChoices      = new \raptor\raptor_datalayer_Choices();
        $bFoundInList = FALSE;  //Initialize
        $aControlOverrides = array();   //Initialize
        
        $enteric_tx = isset($myvalues[$section_name.'_enteric_customtx']) 
                ? $myvalues[$section_name.'_enteric_customtx'] : '';
        $aEntericChoices  = $oChoices
                ->getEntericContrastData($enteric_tx, $bFoundInList);
        if($enteric_tx > '' && !$bFoundInList)
        {
            $aControlOverrides['enteric'] = $enteric_tx;
        }
        
        $iv_tx = isset($myvalues[$section_name.'_iv_customtx']) ? $myvalues[$section_name.'_iv_customtx'] : '';
        $aIVChoices    = $oChoices->getIVContrastData($iv_tx, $bFoundInList);
        if($iv_tx > '' && !$bFoundInList)
        {
            $aControlOverrides['iv'] = $iv_tx;
        }
        
        return $this->getSectionCheckboxType($section_name, $titleoverride, $aEntericChoices
                , $aIVChoices, $form_state, $disabled, $myvalues, $containerstates
                , $supportEditMode
                , $aControlOverrides
                , $shownow
                , $req_ack);
    }
    
    function getYesNoResetRadioTypeSection($section_name, $titleoverride
            , $disabled
            , $myvalues
            , $containerstates
            , $supportEditMode=TRUE
            , $shownow=TRUE
            , $req_ack=FALSE)
    {
        $radioname = $section_name.'_radio_cd';
        if($titleoverride == null)
        {
            $titleoverride = $section_name;
        }
        
        $root = array(
            '#type'     => 'fieldset',
            '#title'    => t($titleoverride),
            '#attributes' => array(
                'class' => array(
                    'data-entry1-area'
                )
             ),
            '#disabled' => $disabled,
        );
        if($containerstates != null)
        {
            $root['#states'] = $containerstates;
        }

        $root[$section_name.'_fieldset_col1'] = array(
            '#type' => 'fieldset',
        );
        //There is no "COL2" in this one by design.
        $root[$section_name.'_fieldset_col3'] = array(
            '#type' => 'fieldset',
        );
        $root[$section_name.'_fieldset_row2'] = array(
            '#type' => 'fieldset',
        );
        
        $options                                           = array(
            'unknown' => t('Unknown'),
            'no'      => t('No'),
            'yes'     => t('Yes'),
        );
        $default_value = isset($myvalues[$radioname]) ? $myvalues[$radioname] : 'unknown';
        
        $root[$section_name.'_fieldset_col1'][$radioname] = array(
            '#type'    => 'radios',
            '#attributes' => array(
                'class' => array('container-inline'),
                ),
            '#options' => $options,
            '#title' => NULL,
        );
        if($default_value !== NULL)
        {
            $root[$section_name.'_fieldset_col1'][$radioname]['#default_value'] = $default_value;
        }
        $root[$section_name.'_fieldset_col1'][$radioname]['#attributes'] 
                = array('onchange' => 'notDefaultValuesInSection("'.$section_name.'")');

        $defaultvalue = isset($myvalues['DefaultValues'][$section_name]) ? $myvalues['DefaultValues'][$section_name] : NULL;
        $root[$section_name.'_fieldset_row2']['showconditionally'] 
                = $this->getDefaultValueControls($section_name, $disabled, $defaultvalue, $req_ack);
        if(!$disabled)
        {
            //Always show this in each section that can have default values!
            $root[$section_name.'_fieldset_col3']['reset_'.$section_name] = array(
                '#markup' => "\n"
                    . '<div class="reset-values-button-container" name="reset-section-values">'
                    . '<a href="javascript:setDefaultValuesInSection('."'".$section_name."',getTemplateDataJSON()".')" title="The default template values will be restored">RESET</a></div>', 
                '#disabled' => $disabled,
            );
        }
        
        $form['main_fieldset_left'][$section_name.'_fieldset'] = &$root;
        return $form;
    }
    

    function getNotesSectionMarkup($section_name, $titleoverride
            , $disabled
            , $myvalues
            , $supportEditMode=TRUE
            , $req_ack=FALSE)
    {
        //$section_name = 'protocolnotes';
        ///$titleoverride = 'Protocol Notes';
        $textfieldname = $section_name.'_tx';
        
        $root                                = array(
            '#type'     => 'fieldset',
            '#title'    => t($titleoverride),
            '#attributes' => array(
                'class' => array(
                    'data-entry2-area'
                )
             ),
            '#disabled' => $disabled,
        );
        $root[$section_name . '_fieldset_col1'] = array(
            '#type' => 'fieldset',
        );
        $root[$section_name . '_fieldset_col3'] = array(
            '#type' => 'fieldset',
        );
        $root[$section_name . '_fieldset_row2'] = array(
            '#type' => 'fieldset',
        );
        if (isset($myvalues[$textfieldname]))
        {
            $protocolnotes_tx = $myvalues[$textfieldname];
        }
        else
        {
            $protocolnotes_tx = '';
        }
        if($disabled && $protocolnotes_tx == '')    //20140714
        {
            //Disabled and empty, dont bother showing any control.
            $root = array();
        } else {
            if ($disabled)
            {
                #A hack to work-around CSS issue on coloring!
                $root[$section_name.'_fieldset_col1']['disabled_'.$textfieldname] = array(
                    '#type'          => 'textarea',
                    '#title'         => t($titleoverride),
                    '#disabled'      => $disabled,
                    '#default_value' => $protocolnotes_tx,
                );
            }
            else
            {
                //Create the boilerplate insertion buttons
                $nBoilerplate     = 0;
                $aBoilerplate     = ListUtils::getCategorizedLists("boilerplate-protocolnotes.cfg");
                $sBoilerplateHTML = "<div id='boilerplate'><ul>";
                foreach ($aBoilerplate as $sCategory => $aContent)
                {
                    $sBoilerplateHTML.="<li class='category'>$sCategory<ul>";
                    foreach ($aContent as $sName => $aItem)
                    {
                        $nBoilerplate+=1;
                        $sTitle = $aItem[0];
                        $sBoilerplateHTML.="<li><a title='$sTitle' onclick='notDefaultValuesInSection(".'"'.$section_name.'"'.")'"
                                . " href='javascript:app2textareaByName(" . '"'. $textfieldname .'"' . "," . '"' . $aItem[0] . '"' 
                                . ")'>$sName</a>";
                    }
                    $sBoilerplateHTML.="</ul>";
                }
                $sBoilerplateHTML.="</ul></div>";
                if ($nBoilerplate > 0)
                {
                    $root[$section_name.'_fieldset_col1']['boilerplate_fieldset']                = array(
                        '#type'  => 'fieldset',
                        '#title' => t($titleoverride.' Boilerplate Text Helpers'),
                    );
                    $root[$section_name.'_fieldset_col1']['boilerplate_fieldset']['boilerplate'] = array(
                        '#markup' => $sBoilerplateHTML,
                    );
                }

                //Create the note area.
                $root[$section_name.'_fieldset_col1'][$textfieldname] = array(
                    '#type'          => 'textarea',
                    '#title'         => t('Protocol Notes'),
                    '#disabled'      => $disabled,
                    '#default_value' => $protocolnotes_tx,
                    '#attributes' => array('oninput' => 'notDefaultValuesInSection("'.$section_name.'")'),
                );
                $defaultvalue = isset($myvalues['DefaultValues'][$section_name]) ? $myvalues['DefaultValues'][$section_name] : NULL;
                $root[$section_name.'_fieldset_row2']['showconditionally'] 
                        = $this->getDefaultValueControls($section_name, $disabled, $defaultvalue, $req_ack);
                if(!$disabled)
                {
                    //Always show this in each section that can have default values!
                    $root[$section_name.'_fieldset_col3']['reset_'.$section_name] = array(
                        '#markup' => "\n".'<div class="reset-values-button-container" name="reset-section-values"><a href="javascript:setDefaultValuesInSection('."'".$section_name."',getTemplateDataJSON()".')" title="The default values for ' . $section_name . ' will be restored">RESET</a></div>', 
                        '#disabled' => $disabled,
                    );
                }
            }
        }
        return $root;
    }

    static function getFirstAvailableValue($myvalues,$aNames,$sDefaultValue)
    {
        foreach($aNames as $sName)
        {
            if(isset($myvalues[$sName]))
            {
                return $myvalues[$sName];
            }
        }
        //Did not find the value already set.
        return $sDefaultValue;
    }
    
    function getConsentReceivedBlock(&$form_state, $disabled, $myvalues, $titleoverride=NULL, $containerstates=NULL)
    {
        if($titleoverride !== NULL)
        {
            $title = t($titleoverride);
        } else {
            $title = t('Consent Received');
        }
        $root = array(
            '#type'     => 'fieldset',
            '#title'    => $title,
            '#description' => t('If consent was required, was it received?  If not required, mark as Not Applicable.'),
            '#attributes' => array(
                'class' => array('container-inline'),
                ),
            '#disabled' => $disabled,
        );
        if($containerstates != null)
        {
            $root['#states'] = $containerstates;
        }

        $options                                     = array(
            'no'    => t('No'),
            'yes'   => t('Yes'),
            'NA'    => t('Not Applicable'),
        );
        $root['exam_consent_received_kw'] = array(
            '#type'    => 'radios',
            '#options' => $options,
            '#title' => '',
        );
        if (isset($myvalues['exam_consent_received_kw']))
        {
            $root['exam_consent_received_kw']['#default_value'] = $myvalues['exam_consent_received_kw'];
        } else {
            //Mark as Not Applicable if protocol says consent is not required.
            if(isset($myvalues['consentreq_radio_cd']) && $myvalues['consentreq_radio_cd'] == 'no')
            {
                $root['exam_consent_received_kw']['#default_value'] = 'NA';
            }
        }

        return $root;
    }
    
    /**
     * The EXAM part of the form.
     */
    function getExamDataEntryFields(&$form_state, $disabled, $myvalues, $protocolValues)
    {
        
        $modality_abbr = $protocolValues['modality_abbr'];
        $protocol_shortname = $protocolValues['protocol_shortname'];
        
        // information.
        $root = array(
            '#type'     => 'fieldset',
            '#title'    => t('Examination Notes'),
            '#attributes' => array(
                'class' => array(
                    'exam-data-entry1-area'
                )
             ),
            '#disabled' => $disabled,
        );
        
        $sFieldsetKeyName = 'exam_contrast_fieldset';
        $root['exam_contrast_fieldset'] = array(
            '#type'  => 'fieldset',
            '#title' => t('Contrast Administered'),
            '#description' => t('Provide the actual contrast administered during the exam'),
        );
        if($this->hasContrastValues($myvalues))
        {
            $root[$sFieldsetKeyName]['#collapsible'] = FALSE;
            $root[$sFieldsetKeyName]['#collapsed'] = FALSE;
        } else {
            $root[$sFieldsetKeyName]['#collapsible'] = TRUE;
            $root[$sFieldsetKeyName]['#collapsed'] = TRUE;
        }
        $sName = 'exam_contrast_enteric_tx';
        $aNames = array($sName,'contrast_enteric_customtx','contrast_enteric_id');
        $default_value = ProtocolInfoUtility::getFirstAvailableValue($myvalues, $aNames, '');
        $root['exam_contrast_fieldset'][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t('Enteric Type'),
            '#maxlength' => 100,
            '#default_value' => $default_value,
        );
        $sName = 'exam_contrast_iv_tx';
        $aNames = array($sName,'contrast_iv_customtx','contrast_iv_id');
        $default_value = ProtocolInfoUtility::getFirstAvailableValue($myvalues, $aNames, '');
        $root['exam_contrast_fieldset'][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t('IV Type'),
            '#maxlength' => 100,
            '#default_value' => $default_value,
        );

        $sFieldsetKeyName = 'exam_hydration_fieldset';
        $root[$sFieldsetKeyName] = array(
            '#type'  => 'fieldset',
            '#title' => t('Hydration Administered'),
            '#description' => t('Provide the actual hydration administered during the exam'),
        );
        if($this->hasHydrationValues($myvalues))
        {
            $root[$sFieldsetKeyName]['#collapsible'] = FALSE;
            $root[$sFieldsetKeyName]['#collapsed'] = FALSE;
        } else {
            $root[$sFieldsetKeyName]['#collapsible'] = TRUE;
            $root[$sFieldsetKeyName]['#collapsed'] = TRUE;
        }
        $sName = 'exam_hydration_oral_tx';
        $aNames = array($sName,'hydration_oral_customtx','hydration_oral_id');
        $default_value = ProtocolInfoUtility::getFirstAvailableValue($myvalues, $aNames, '');
        $root['exam_hydration_fieldset'][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t('Oral Type'),
            '#maxlength' => 100,
            '#default_value' => $default_value,
        );
        $sName = 'exam_hydration_iv_tx';
        $aNames = array($sName,'hydration_iv_customtx','hydration_iv_id');
        $default_value = ProtocolInfoUtility::getFirstAvailableValue($myvalues, $aNames, '');
        $root['exam_hydration_fieldset'][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t('IV Type'),
            '#maxlength' => 100,
            '#default_value' => $default_value,
        );
        
        $sFieldsetKeyName = 'exam_sedation_fieldset';
        $root[$sFieldsetKeyName] = array(
            '#type'  => 'fieldset',
            '#title' => t('Sedation Administered'),
            '#description' => t('Provide the actual sedation administered during the exam'),
        );
        if($this->hasSedationValues($myvalues))
        {
            $root[$sFieldsetKeyName]['#collapsible'] = FALSE;
            $root[$sFieldsetKeyName]['#collapsed'] = FALSE;
        } else {
            $root[$sFieldsetKeyName]['#collapsible'] = TRUE;
            $root[$sFieldsetKeyName]['#collapsed'] = TRUE;
        }
        $sName = 'exam_sedation_oral_tx';
        $aNames = array($sName,'sedation_oral_customtx','sedation_oral_id');
        $default_value = ProtocolInfoUtility::getFirstAvailableValue($myvalues, $aNames, '');
        $root['exam_sedation_fieldset'][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t('Oral Type'),
            '#maxlength' => 100,
            '#default_value' => $default_value,
        );
        $sName = 'exam_sedation_iv_tx';
        $aNames = array($sName,'sedation_iv_customtx','sedation_iv_id');
        $default_value = ProtocolInfoUtility::getFirstAvailableValue($myvalues, $aNames, '');
        $root['exam_sedation_fieldset'][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t('IV Type'),
            '#maxlength' => 100,
            '#default_value' => $default_value,
        );

        $bGivenRadioisotope = $this->hasRadioisotopeValues($myvalues);
        $bMachineEmitsRadiation = $modality_abbr == 'CT'
                    || $modality_abbr == 'FL'
                    || $modality_abbr == 'NM';
        
        $sFieldsetKeyName = 'exam_radioisotope_fieldset';
        $root[$sFieldsetKeyName] = array(
            '#type'  => 'fieldset',
            '#title' => t('Radioisotope Administered'),
            '#description' => t('Provide the actual radioisotope administered during the exam'),
        );
        if($bGivenRadioisotope)
        {
            $root[$sFieldsetKeyName]['#collapsible'] = FALSE;
            $root[$sFieldsetKeyName]['#collapsed'] = FALSE;
        } else {
            $root[$sFieldsetKeyName]['#collapsible'] = TRUE;
            $root[$sFieldsetKeyName]['#collapsed'] = TRUE;
        }
        
        $sName = 'exam_radioisotope_enteric_tx';
        $aNames = array($sName,'radioisotope_enteric_customtx','radioisotope_enteric_id');
        $default_value = ProtocolInfoUtility::getFirstAvailableValue($myvalues, $aNames, '');
        $root[$sFieldsetKeyName][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t('Enteric Type'),
            '#maxlength' => 100,
            '#default_value' => $default_value,
        );
        $sName = 'exam_radioisotope_iv_tx';
        $aNames = array($sName,'radioisotope_iv_customtx','radioisotope_iv_id');
        $default_value = ProtocolInfoUtility::getFirstAvailableValue($myvalues, $aNames, '');
        $root[$sFieldsetKeyName][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t('IV Type'),
            '#maxlength' => 100,
            '#default_value' => $default_value,
        );
        if(isset($myvalues['exam_radioisotope_radiation_dose_tx']) || isset($myvalues['exam_radioisotope_radiation_dose_uom_tx']))
        {
            //Use this value if we find it.
            $default_dose_value = isset($myvalues['exam_radioisotope_radiation_dose_tx']) ? $myvalues['exam_radioisotope_radiation_dose_tx'] : '';
            $default_dose_uom = isset($myvalues['exam_radioisotope_radiation_dose_uom_tx']) ? $myvalues['exam_radioisotope_radiation_dose_uom_tx'] : '';
            $default_dose_value_type_cd = isset($myvalues['exam_radioisotope_radiation_dose_type_cd']) ? $myvalues['exam_radioisotope_radiation_dose_type_cd'] : '';
        } else {
            //Derive a default from the dose map.
            $default_dose_value = NULL;
            $default_dose_uom = NULL;
            $default_dose_value_type_cd = NULL;
            if(isset($myvalues['exam_radioisotope_radiation_dose_map']))
            {
                $dose_map = $myvalues['exam_radioisotope_radiation_dose_map'];
                $a = $this->getDefaultRadiationDoseValuesForForm($dose_map);
                $default_dose_uom = $a['uom'];
                $default_dose_value = $a['dose'];
                $default_dose_value_type_cd = $a['dose_type_cd'];
            }
        }
        if($default_dose_value == NULL)
        {
            $default_dose_value = '';
        }
        $sName = 'exam_radioisotope_radiation_dose_tx';
        $root[$sFieldsetKeyName][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t('Radiation Dose Values'),
            '#size' => 100,
            '#maxlength' => 256,
            '#default_value' => $default_dose_value,
            '#description' => t('Provide the radioisotope radiation exposure during the exam.  If there are multiple doses, delimit each dose with a comma.'),
        );
        if($default_dose_uom == NULL)
        {
            $default_dose_uom = 'mGy';
        }
        $sName = 'exam_radioisotope_radiation_dose_uom_tx';
        $root[$sFieldsetKeyName][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t('Radiation Dose Units'),
            '#size' => 8,
            '#maxlength' => 32,
            '#default_value' => $default_dose_uom,
            '#description' => t('Provide the radioisotope radiation unit of measure for the dose(s) recorded here'),
        );
        $aDoseTypeOptions = array();
        $aDoseTypeOptions['A'] = t('Actual');
        $aDoseTypeOptions['E'] = t('Estimate');
	$sDoseTypeRadiosTitle = 'Dose Value Type';
        $sName = 'exam_radioisotope_radiation_dose_type_cd';
        $root[$sFieldsetKeyName][$sName] = array(
            '#type' => 'radios',
            '#attributes' => array(
                'class' => array('container-inline'),
                ),
            '#options' => $aDoseTypeOptions,
            '#disabled' => $disabled,
            '#title' => t($sDoseTypeRadiosTitle),   //Important to have title otherwise required symbol not shown! 
        );        
        if($default_dose_value_type_cd != '')
        {
            $root[$sFieldsetKeyName][$sName]['#default_value'] = $default_dose_value_type_cd;
        }
        
        
        //Get the inputs for device dose
        if($bMachineEmitsRadiation)
        {
            if($modality_abbr == 'CT')
            {
                //Specifically a CT device
                $this->addFormDeviceRadiationDoseGroup($root, $myvalues, $disabled
                        , 'exam_ctdivol_dose_fieldset', 'Device Radiation Dose CTDIvol', 'CTDIvol', 'CTDIvol', 'mGycm' 
                        , 'exam_ctdivol_radiation_dose_map'
                        , 'exam_ctdivol_radiation_dose_tx'
                        , 'exam_ctdivol_radiation_dose_uom_tx'
                        , 'exam_ctdivol_radiation_dose_type_cd');
                $this->addFormDeviceRadiationDoseGroup($root, $myvalues, $disabled
                        , 'exam_dlp_dose_fieldset', 'Device Radiation Dose DLP', 'DLP', 'DLP', 'mGy'
                        , 'exam_dlp_radiation_dose_map'
                        , 'exam_dlp_radiation_dose_tx'
                        , 'exam_dlp_radiation_dose_uom_tx'
                        , 'exam_dlp_radiation_dose_type_cd');
            } else {
                //Other kind of device
                $this->addFormDeviceRadiationDoseGroup($root, $myvalues, $disabled
                        , 'exam_other_dose_fieldset', 'Device Radiation Dose Other', 'Other', 'other', 'mGy'
                        , 'exam_other_radiation_dose_map'
                        , 'exam_other_radiation_dose_tx'
                        , 'exam_other_radiation_dose_uom_tx'
                        , 'exam_other_radiation_dose_type_cd');
            }
        }
        $root['exam_consent_received_fieldset'] = $this->getConsentReceivedBlock($form_state, $disabled, $myvalues);
        
        $sName = 'exam_notes_tx';
        $default_value = isset($myvalues[$sName]) ? $myvalues[$sName] : '';
        if ($disabled)
        {
            if(trim($default_value) > '')
            {
                //A hack to work-around CSS issue on coloring!
                $root['exam_summary'][$sName] = array(
                    '#type'     => 'textarea',
                    '#title'    => t('Examination Comments'),
                    '#default_value' => $default_value,
                    '#disabled' => $disabled,
                );
            }
        }
        else
        {
            $root['exam_summary'][$sName] = array(
                '#type'     => 'textarea',
                '#rows'     => 20,
                '#title'    => t('Examination Comments'),
                '#maxlength' => 1024,
                '#default_value' => $default_value,
                '#disabled' => $disabled,
            );
        }
        return $root;
    }
    
    /**
     * Add equipment dose radiation capture to the form markup array
     */
    function addFormDeviceRadiationDoseGroup(&$root, $myvalues, $disabled
            , $sFieldsetKeyName, $sectiontitle, $itemprefix_distinction, $inlinedistinction, $default_uom
            , $map_valuename
            , $dose_valuesname, $uom_valuename, $typecd_valuename)
    {
        $root[$sFieldsetKeyName] = array(
            '#type'  => 'fieldset',
            '#title' => t($sectiontitle),   //'Equipment Radiation Dose Exposure'),
        );
        if(isset($myvalues[$dose_valuesname]) || isset($myvalues[$uom_valuename]))
        {
            //Use this value if we find it.
            $default_dose_value = isset($myvalues[$dose_valuesname]) ? $myvalues[$dose_valuesname] : '';
            $default_dose_uom = isset($myvalues[$uom_valuename]) ? $myvalues[$uom_valuename] : '';
            $default_dose_value_type_cd = isset($myvalues[$typecd_valuename]) ? $myvalues[$typecd_valuename] : '';
        } else {
            //Derive a default from the dose map.
            $default_dose_value = NULL;
            $default_dose_uom = NULL;
            $default_dose_value_type_cd = NULL;
            if(isset($myvalues[$map_valuename]))
            {
                $dose_map = $myvalues[$map_valuename];
                $a = $this->getDefaultRadiationDoseValuesForForm($dose_map);
                $default_dose_uom = $a['uom'];
                $default_dose_value = $a['dose'];
                $default_dose_value_type_cd = $a['dose_type_cd'];
            }
        }
        
        if($default_dose_value == NULL)
        {
            $default_dose_value = '';
        }
        $sName = $dose_valuesname;
        $root[$sFieldsetKeyName][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t($itemprefix_distinction.' Radiation Dose Values'),
            '#size' => 100,
            '#maxlength' => 256,
            '#default_value' => $default_dose_value,
            '#description' => t('Provide the actual '.$inlinedistinction.' equipment radiation exposure during the exam.  If there are multiple doses, delimit each dose with a comma.'),
        );
        if($default_dose_uom == NULL)
        {
            $default_dose_uom = $default_uom;
        }
        $sName = $uom_valuename;
        $root[$sFieldsetKeyName][$sName] = array(
            '#type'  => 'textfield',
            '#title' => t($itemprefix_distinction.' Radiation Dose Units'),
            '#size' => 8,
            '#maxlength' => 32,
            '#default_value' => $default_dose_uom,
            '#description' => t('Provide the unit of measure for the '.$inlinedistinction.' equipment dose(s) recorded here'),
        );
        $aDoseTypeOptions = array();
        $aDoseTypeOptions['A'] = t('Actual');
        $aDoseTypeOptions['E'] = t('Estimate');
	$sDoseTypeRadiosTitle = 'Dose Value Type';
        $sName = $typecd_valuename;
        $root[$sFieldsetKeyName][$sName] = array(
            '#type' => 'radios',
            '#attributes' => array(
                'class' => array('container-inline'),
                ),
            '#options' => $aDoseTypeOptions,
            '#disabled' => $disabled,
            '#title' => t($sDoseTypeRadiosTitle),   //Important to have title otherwise required symbol not shown! 
        );        
        if($default_dose_value_type_cd != '')
        {
            $root[$sFieldsetKeyName][$sName]['#default_value'] = $default_dose_value_type_cd;
        }
    }

    /**
     * Construct the text fields for the form from the dose map array
     */
    function getDefaultRadiationDoseValuesForForm($dose_map)
    {                
        $uom_values = array();
        $dose_values = array();
        $dose_type_cd_values = array();
        if(!is_array($dose_map))
        {
            $default_dose_value = NULL;
            $default_dose_uom = NULL;
            $dose_type_cd = NULL;
        } else {
            foreach($dose_map as $uom=>$dose_records)
            {
                $uom_values[$uom] = $uom;
                foreach($dose_records as $dose_record)
                {
                    $dose = $dose_record['dose'];
                    $dose_values[] = $dose;
                    $type = $dose_record['dose_type_cd'];
                    $dose_type_cd_values[$type] = $type;
                }
            }
            if(count($uom_values)>0)
            {
                $default_dose_uom = implode('?', $uom_values);  //If we have multiple units add question mark!
            } else {
                $default_dose_uom = '';
            }
            if(count($dose_type_cd_values)>0)
            {
                $dose_type_cd = implode('?', $dose_type_cd_values);  //If we have multiple units add question mark!
            } else {
                $dose_type_cd = '';
            }
            if(count($dose_values)>0)
            {
                $default_dose_value = implode(',', $dose_values);   //List of values.
            } else {
                $default_dose_value = '';
            }
        }

        return array('dose'=>$default_dose_value, 'uom'=>$default_dose_uom
                , 'dose_type_cd'=>$dose_type_cd
                , 'dose_type_cd_values'=>$dose_type_cd_values);
    }    
    
    /**
     * The INTERPRETATION part of the form.
     */
    function getInterpretationDataEntryFields(&$form_state, $disabled, $myvalues)
    {
        // information.
        $root = array(
            '#type'     => 'fieldset',
            '#title'    => t('Interpretation Notes'),
            '#attributes' => array(
                'class' => array(
                    'interpretation-data-entry1-area'
                )
             ),
            '#disabled' => $disabled,
        );
        
        $sName = 'interpret_notes_tx';
        $default_value = isset($myvalues[$sName]) ? $myvalues[$sName] : '';
        $root['interpretation_summary'][$sName] = array(
            '#type'     => 'textarea',
            '#rows'     => 20,
            '#title'    => t('Interpretation Comments'),
            '#maxlength' => 1024,
            '#default_value' => $default_value,
            '#disabled' => $disabled,
        );
        return $root;
    }    

    /**
     * The QA part of the form.
     */
    function getQADataEntryFields(&$form_state, $disabled, $myvalues)
    {
        // information.
        $root['qa_summary_fieldset'] = array(
            '#type'     => 'fieldset',
            '#title'    => t('QA Notes'),
            '#attributes' => array(
                'class' => array(
                    'qa-data-entry1-area'
                )
             ),
            '#disabled' => $disabled,
        );

        $qa_scores = array(  0  =>  t('Not Evaluated')
                            ,1  =>  t('Needs significant improvement')
                            ,2  =>  t('Needs improvement')
                            ,3  =>  t('Satisfactory')
                            ,4  =>  t('Very good')
                            ,5  =>  t('Outstanding')
            );
        $result = db_select('raptor_qa_criteria', 'n')
                ->fields('n')
                ->condition('context_cd', 'T','=')
                ->orderBy('position')
                ->execute();
        $qmarkup = array();
        while($record = $result->fetchAssoc())
        {
            $version = trim($record['version']);
            $shortname = trim($record['shortname']);
            $question = trim($record['question']);
            $explanation = trim($record['explanation']);
            $myitem = array(
             '#type' => 'radios',
             '#title' => t($question),
             '#options' => $qa_scores,
             '#description' => t($explanation),
             '#attributes' => array(
                'class' => array('container-inline'),
                ),
            );   

            if(isset($myvalues[$shortname]['score']))
            {
                $myitem['#default_value'] = $myvalues[$shortname]['score'];
            }
            $mycomment = array(
                '#type' => 'textarea',
                '#rows'     => 1,
                '#maxlength' => 1024,
            );
            if(isset($myvalues[$shortname]['comment']))
            {
                $mycomment['#default_value'] = $myvalues[$shortname]['comment'];
            }

            $qmarkup[$shortname]['version'] =  array('#type' => 'hidden', '#value' => $version);
            $qmarkup[$shortname]['score'] = $myitem;
            $qmarkup[$shortname]['comment'] = $mycomment;
        }
        if(count($qmarkup)>0)
        {
            $root['qa_summary_fieldset']['evaluations'] = array(
                '#type'     => 'fieldset',
                '#title'    => t('Assessment Questions'),
                '#disabled' => $disabled,
                '#tree' => TRUE,
            );
            foreach($qmarkup as $key=>$value)
            {
                $root['qa_summary_fieldset']['evaluations'][$key] = $value;
            }
        }
        
        //Show the general comment input.
        $sName = 'qa_notes_tx';
        $default_value = isset($myvalues[$sName]) ? $myvalues[$sName] : '';
        $root['qa_summary_fieldset']['overall'][$sName] = array(
            '#type'     => 'textarea',
            '#rows'     => 10,
            '#title'    => t('Overall QA Comments'),
            '#maxlength' => 1024,
            '#default_value' => $default_value,
            '#disabled' => $disabled,
        );
        return $root;
    }    
    
    
    /**
     * The QA stuff
     */
    function raptor_form_get_postexam_info($form, &$form_state, $disabled, $myvalues, $supportEditMode=TRUE)
    {

        // information.
        $root = array(
            '#type'  => 'fieldset',
            '#title' => t('Post-Examination Notes'),
            '#attributes' => array(
                'class' => array(
                    'data-entry2-area'
                )
             ),
        );

        //Show all the existing notes
        $q        = db_select('raptor_form_qa', 'a');
        $q->addField('a', 'rf_id');
        $q->addField('a', 'user_id');
        $q->addField('a', 'qa1_fl');
        $q->addField('a', 'qa2_fl');
        $q->addField('a', 'qa3_fl');
        $q->addField('a', 'qa4_fl');
        $q->addField('a', 'qa5_fl');
        $q->addField('a', 'qa_notes_tx');
        $q->addField('a', 'created_dt');
        $q->condition('rf_id', $myvalues['guid']);
        $rf       = $q->execute();
        $myvalues = array();
        if ($rf->rowCount() > 0)
        {
            $sHTML = "<table id='qa-notes'>";
            $sHTML.="<tr>";
            $sHTML.="<th width='20%'>Datetime</th>";
            $sHTML.="<th width='20%'>Author</th>";
            $sHTML.="<th>QA1</th>";
            $sHTML.="<th>QA2</th>";
            $sHTML.="<th>QA3</th>";
            $sHTML.="<th>QA4</th>";
            $sHTML.="<th>QA5</th>";
            $sHTML.="<th width='35%'>Notes</th>";
            $sHTML.="</tr>";
            $nROW  = 0;
            foreach ($rf as $row)
            {
                $nUID    = $row->user_id;
                //$oOtherUser=UserInfo::lookupUserByUID( $nUID );
                $theName = UserInfo::lookupUserNameByUID($nUID);
                $nROW+=1;
                $sHTML.="<tr>";
                $sHTML.="<td width='20%'>" . $row->created_dt . "</td>";
                $sHTML.="<td width='20%'>" . $theName . "</td>";
                //$sHTML.="<td>".$oOtherUser->name."<td>";
                $sHTML.="<td>" . ($row->qa1_fl == 1 ? '<b>Yes</b>' : 'No') . "</td>";
                $sHTML.="<td>" . ($row->qa2_fl == 1 ? '<b>Yes</b>' : 'No') . "</td>";
                $sHTML.="<td>" . ($row->qa3_fl == 1 ? '<b>Yes</b>' : 'No') . "</td>";
                $sHTML.="<td>" . ($row->qa4_fl == 1 ? '<b>Yes</b>' : 'No') . "</td>";
                $sHTML.="<td>" . ($row->qa5_fl == 1 ? '<b>Yes</b>' : 'No') . "</td>";
                $sHTML.="<td width='35%'>" . $row->qa_notes_tx . "</td>";
                $sHTML.="</tr>";
            }
            $sHTML.="</table>";
            $root['qa_notes' . $nROW] = array(
                '#markup' => $sHTML,
                    //'#markup' => '<p>HELLO TESTING</p>'."<br>ROW:".var_export($row,true),
            );
        }

        $oGrp1                          = $root['postexam_fieldset_col1'] = array(
            '#type' => 'fieldset',
                //'#title' => t('COL1'),
        );

        $root['postexam_fieldset_col1']['postexam_qa_fieldset'] = array(
            '#type'  => 'fieldset',
            '#title' => t('QA Issues'),
        );

        $root['postexam_fieldset_col1']['postexam_qa_fieldset']['postexam_qa_fl'] = array(
            '#type'    => 'checkboxes',
            '#options' => array(
                'QA1' => t('QA1'),
                'QA2' => t('QA2'),
                'QA3' => t('QA3'),
                'QA4' => t('QA4'),
                'QA5' => t('QA5'),
            ),
                //'#title' => t('contrast')
        );

        $oGrp2                          = $root['postexam_fieldset_col2'] = array(
            '#type' => 'fieldset',
                //'#title' => t('COL2'),
        );

        $root['postexam_fieldset_col2']['qa_notes_tx'] = array(
            '#type'     => 'textarea',
            '#title'    => t('Post-Examination Notes'),
            '#disabled' => $disabled
        );

        $form['main_fieldset_bottom']['postexam_fieldset'] = &$root;
        return $form;
    }

    /**
     * Saves values when in protocol mode.
     * @param type $nSiteID
     * @param type $nIEN
     * @param type $nUID
     * @param type $sCWFS
     * @param type $sNewWFS
     * @param type $updated_dt
     * @param type $myvalues
     * @return int
     */
    public function saveAllProtocolFieldValues($nSiteID, $nIEN, $nUID, $sCWFS, $sNewWFS, $updated_dt, $myvalues)
    {
        //Create the raptor_ticket_protocol_settings record now
        $bSuccess = TRUE;
        try
        {
            //See if we already have a record of values.
            $result = db_select('raptor_ticket_protocol_settings','p')
                    ->fields('p')
                    ->condition('siteid',$nSiteID,'=')
                    ->condition('IEN',$nIEN,'=')
                    ->execute();
            $nRows = $result->rowCount();
            if($nRows > 0)
            {
                //Replace the record but save the values.
                error_log('Replacing protocol information SITEID=' . $nSiteID . ' IEN=' . $nIEN . ' UID=' . $nUID . ' CWFS=' . $sCWFS . ' NWFS=' . $sNewWFS);
                $record = $result->fetchAssoc();
                $oInsert = db_insert('raptor_ticket_protocol_settings_replaced')
                        ->fields(array(
                            'siteid' =>$record['siteid'],
                            'IEN' => $record['IEN'],
                            'primary_protocol_shortname' => $record['primary_protocol_shortname'],
                            'secondary_protocol_shortname' => $record['secondary_protocol_shortname'],
                            'current_workflow_state_cd' => $record['current_workflow_state_cd'],

                            'hydration_none_yn' => $record['hydration_none_yn'],
                            'hydration_oral_tx' => $record['hydration_oral_tx'],
                            'hydration_iv_tx' => $record['hydration_iv_tx'],

                            'sedation_none_yn' => $record['sedation_none_yn'],
                            'sedation_oral_tx' => $record['sedation_oral_tx'],
                            'sedation_iv_tx' => $record['sedation_iv_tx'],
                            
                            'contrast_none_yn' => $record['contrast_none_yn'],
                            'contrast_enteric_tx' => $record['contrast_enteric_tx'],
                            'contrast_iv_tx' => $record['contrast_iv_tx'],

                            'radioisotope_none_yn' => $record['radioisotope_none_yn'],
                            'radioisotope_enteric_tx' => $record['radioisotope_enteric_tx'],
                            'radioisotope_iv_tx' => $record['radioisotope_iv_tx'],

                            'author_uid' => $record['author_uid'],
                            'original_created_dt' => $record['created_dt'],
                            'replaced_dt' => $updated_dt,
                        ))
                        ->execute();
                $nDeleted = db_delete('raptor_ticket_protocol_settings')
                    ->condition('siteid',$nSiteID,'=')
                    ->condition('IEN',$nIEN,'=')
                    ->execute();
            }

            //die('LOOK>>>'. print_r($myvalues,TRUE)); 
            if($myvalues['hydration_radio_cd'] == 'oral')
            {
                $hydration_oral_value = trim($myvalues['hydration_oral_customtx']);
                if($hydration_oral_value == NULL || $hydration_oral_value == '')
                {
                    $hydration_oral_value = trim($myvalues['hydration_oral_id']);   //Todo rename because not really an ID
                }
            } else {
                $hydration_oral_value = NULL;
            }
            if($myvalues['hydration_radio_cd'] == 'iv')
            {
                $hydration_iv_value = trim($myvalues['hydration_iv_customtx']);
                if($hydration_iv_value == NULL || $hydration_iv_value == '')
                {
                    $hydration_iv_value = trim($myvalues['hydration_iv_id']);   //Todo rename because not really an ID
                }
            } else {
                $hydration_iv_value = NULL;
            }
            if($myvalues['sedation_radio_cd'] == 'oral')
            {
                $sedation_oral_value = trim($myvalues['sedation_oral_customtx']);
                if($sedation_oral_value == NULL || $sedation_oral_value == '')
                {
                    $sedation_oral_value = trim($myvalues['sedation_oral_id']);   //Todo rename because not really an ID
                }
            } else {
                $sedation_oral_value = NULL;
            }
            if($myvalues['sedation_radio_cd'] == 'iv')
            {
                $sedation_iv_value = trim($myvalues['sedation_iv_customtx']);
                if($sedation_iv_value == NULL || $sedation_iv_value == '')
                {
                    $sedation_iv_value = trim($myvalues['sedation_iv_id']);   //Todo rename because not really an ID
                }
            } else {
                $sedation_iv_value = NULL;
            }
            
            
            $contrast_enteric_value = NULL;
            $contrast_iv_value = NULL;
            $myarray = $myvalues['contrast_cd'];
            if($myarray['none'] !== 0)
            {
                //No contrast selected.
                $contrast_none = 1;
            } else {
                //Yes, contrast selected.
                $contrast_none = 0;
                if($myarray['enteric'] !== 0)
                {
                    $contrast_enteric_value = (isset($myvalues['contrast_enteric_customtx']) ? trim($myvalues['contrast_enteric_customtx']) : '');
                    if($contrast_enteric_value == NULL || $contrast_enteric_value == '')
                    {
                        $contrast_enteric_value = trim($myvalues['contrast_enteric_id']);   //Todo rename because not really an ID
                    }
                }
                if($myarray['iv'] !== 0)
                {
                    $contrast_iv_value = (isset($myvalues['contrast_iv_customtx']) ? trim($myvalues['contrast_iv_customtx']) : '');
                    if($contrast_iv_value == NULL || $contrast_iv_value == '')
                    {
                        $contrast_iv_value = trim($myvalues['contrast_iv_id']);   //Todo rename because not really an ID
                    }
                }            
            }
            
            $radioisotope_enteric_value = NULL;
            $radioisotope_iv_value = NULL;
            $myarray = $myvalues['radioisotope_cd'];
            if($myarray['none'] !== 0)
            {
                //No radioisotope selected.
                $radioisotope_none = 1;
            } else {
                //Yes, radioisotope selected.
                $radioisotope_none = 0;
                if($myarray['enteric'] !== 0)
                {
                    $radioisotope_enteric_value = (isset($myvalues['radioisotope_enteric_customtx']) ? trim($myvalues['radioisotope_enteric_customtx']) : '');
                    if($radioisotope_enteric_value == NULL || $radioisotope_enteric_value == '')
                    {
                        $radioisotope_enteric_value = trim($myvalues['radioisotope_enteric_id']);   //Todo rename because not really an ID
                    }
                }
                if($myarray['iv'] !== 0)
                {
                    $radioisotope_iv_value = (isset($myvalues['radioisotope_iv_customtx']) ? trim($myvalues['radioisotope_iv_customtx']) : '');
                    if($radioisotope_iv_value == NULL || $radioisotope_iv_value == '')
                    {
                        $radioisotope_iv_value = trim($myvalues['radioisotope_iv_id']);   //Todo rename because not really an ID
                    }
                }            
            }
            
            $allergy_kw = isset($myvalues['allergy_cd']) ? $myvalues['allergy_cd'] : NULL;
            $claustrophobic_kw = isset($myvalues['claustrophobic_cd']) ? $myvalues['claustrophobic_cd'] : NULL;
            $consent_req_kw = isset($myvalues['consentreq_radio_cd']) ? $myvalues['consentreq_radio_cd'] : NULL;
            
            $oInsert = db_insert('raptor_ticket_protocol_settings')
                    ->fields(array(
                        'siteid' => $nSiteID,
                        'IEN' => $nIEN,
                        
                        'primary_protocol_shortname' => $myvalues['protocol1_nm'],
                        'secondary_protocol_shortname' => $myvalues['protocol2_nm'],
                        
                        'hydration_none_yn' => ($myvalues['hydration_radio_cd'] == '' ? 1 : 0),
                        'hydration_oral_tx' => $hydration_oral_value,
                        'hydration_iv_tx' => $hydration_iv_value,
                        
                        'sedation_none_yn' => ($myvalues['sedation_radio_cd'] == '' ? 1 : 0),
                        'sedation_oral_tx' => $sedation_oral_value,
                        'sedation_iv_tx' => $sedation_iv_value,
                        
                        'contrast_none_yn' => $contrast_none,
                        'contrast_enteric_tx' => $contrast_enteric_value,
                        'contrast_iv_tx' => $contrast_iv_value,

                        'radioisotope_none_yn' => $radioisotope_none,
                        'radioisotope_enteric_tx' => $radioisotope_enteric_value,
                        'radioisotope_iv_tx' => $radioisotope_iv_value,

                        'allergy_kw' => $allergy_kw,
                        'claustrophobic_kw' => $claustrophobic_kw,
                        'consent_req_kw' => $consent_req_kw,
                        
                        'current_workflow_state_cd' => $sCWFS,
                        'author_uid' => $nUID,
                        'created_dt' => $updated_dt,
                    ))
                    ->execute();
        }
        catch(\Exception $e)
        {
            error_log('Failed to create raptor_ticket_protocol_settings: ' . print_r($e,TRUE));
            drupal_set_message('Failed to save information for this ticket because ' . $e->getMessage(),'error');
            $bSuccess = FALSE;
        }

        if($bSuccess)
        {
            //Create the raptor_ticket_protocol_notes record now
            try
            {
                if(isset($myvalues['protocolnotes_tx']) && trim($myvalues['protocolnotes_tx']) !== '')
                {
                    $oInsert = db_insert('raptor_ticket_protocol_notes')
                            ->fields(array(
                                'siteid' => $nSiteID,
                                'IEN' => $nIEN,
                                'notes_tx' => $myvalues['protocolnotes_tx'],
                                'author_uid' => $nUID,
                                'created_dt' => $updated_dt,
                            ))
                            ->execute();
                }
            }
            catch(\Exception $e)
            {
                error_log('Failed to create raptor_ticket_protocol_notes: ' . $e);
                form_set_error('protocol1_nm','Failed to save notes for this ticket!');
                $bSuccess = FALSE;
            }
        }

        if($bSuccess && $sNewWFS != $sCWFS)
        {
            $this->changeTicketWorkflowStatus($nSiteID, $nIEN, $nUID, $sNewWFS, $sCWFS, $updated_dt);
        }
        return $bSuccess;
    }

    /**
     * Saves values when in exam mode.
     * @return boolean
     */
    public function saveAllExamFieldValues($nSiteID, $nIEN, $nUID, $sCWFS, $sNewWFS, $updated_dt, $myvalues)
    {
        $patientDFN = NULL;
        try
        {
            $oDD = new \raptor\DashboardData($this->m_oContext);
            $raptor_protocoldashboard = $oDD->getDashboardDetails();
            $patientDFN=$raptor_protocoldashboard['PatientID'];
        } catch (\Exception $ex) {
            throw new \Exception('Failed to get the dashboard to save exam fields',91111,$ex);
        }
            
        //Create the raptor_ticket_exam_settings record now
        $bSuccess = TRUE;
        try
        {
            //See if we already have a record of values.
            $result = db_select('raptor_ticket_exam_settings','p')
                    ->fields('p')
                    ->condition('siteid',$nSiteID,'=')
                    ->condition('IEN',$nIEN,'=')
                    ->execute();
            $nRows = $result->rowCount();
            if($nRows > 0)
            {
                //Replace the record but save the values.
                error_log('Replacing exam information SITEID=' . $nSiteID . ' IEN=' . $nIEN . ' UID=' . $nUID . ' CWFS=' . $sCWFS . ' NWFS=' . $sNewWFS);
                $record = $result->fetchAssoc();
                $oInsert = db_insert('raptor_ticket_exam_settings_replaced')
                        ->fields(array(
                            'siteid' =>$record['siteid'],
                            'IEN' => $record['IEN'],
                            'current_workflow_state_cd' => $record['current_workflow_state_cd'],

                            'hydration_none_yn' => $record['hydration_none_yn'],
                            'hydration_oral_tx' => $record['hydration_oral_tx'],
                            'hydration_iv_tx' => $record['hydration_iv_tx'],

                            'sedation_none_yn' => $record['sedation_none_yn'],
                            'sedation_oral_tx' => $record['sedation_oral_tx'],
                            'sedation_iv_tx' => $record['sedation_iv_tx'],
                            
                            'contrast_none_yn' => $record['contrast_none_yn'],
                            'contrast_enteric_tx' => $record['contrast_enteric_tx'],
                            'contrast_iv_tx' => $record['contrast_iv_tx'],

                            'radioisotope_none_yn' => $record['radioisotope_none_yn'],
                            'radioisotope_enteric_tx' => $record['radioisotope_enteric_tx'],
                            'radioisotope_iv_tx' => $record['radioisotope_iv_tx'],
                            
                            'consent_received_kw' => $record['consent_received_kw'],
                            
                            'author_uid' => $record['author_uid'],
                            'original_created_dt' => $record['created_dt'],
                            'replaced_dt' => $updated_dt,
                        ))
                        ->execute();
                $nDeleted = db_delete('raptor_ticket_exam_settings')
                    ->condition('siteid',$nSiteID,'=')
                    ->condition('IEN',$nIEN,'=')
                    ->execute();
            }

            $hydration_oral_value = trim($myvalues['exam_hydration_oral_tx']);
            $hydration_iv_value = trim($myvalues['exam_hydration_iv_tx']);
            $hydration_none_yn = ($hydration_oral_value == '' && $hydration_iv_value == '') ? 1 : 0;   
                
            $sedation_oral_value = trim($myvalues['exam_sedation_oral_tx']);
            $sedation_iv_value = trim($myvalues['exam_sedation_iv_tx']);
            $sedation_none_yn = ($sedation_oral_value == '' && $sedation_iv_value == '') ? 1 : 0;   

            $contrast_enteric_value = trim($myvalues['exam_contrast_enteric_tx']);
            $contrast_iv_value = trim($myvalues['exam_contrast_iv_tx']);
            $contrast_none_yn = ($contrast_enteric_value == '' && $contrast_iv_value == '') ? 1 : 0;   

            $radioisotope_enteric_value = trim($myvalues['exam_radioisotope_enteric_tx']);
            $radioisotope_iv_value = trim($myvalues['exam_radioisotope_iv_tx']);
            $radioisotope_none_yn = ($radioisotope_enteric_value == '' && $radioisotope_iv_value == '') ? 1 : 0;   

            $consent_received_kw = isset($myvalues['exam_consent_received_kw']) ? $myvalues['exam_consent_received_kw'] : NULL;
            
            $oInsert = db_insert('raptor_ticket_exam_settings')
                    ->fields(array(
                        'siteid' => $nSiteID,
                        'IEN' => $nIEN,
                        
                        'hydration_none_yn' => $hydration_none_yn,
                        'hydration_oral_tx' => $hydration_oral_value,
                        'hydration_iv_tx' => $hydration_iv_value,
                        
                        'sedation_none_yn' => $sedation_none_yn,
                        'sedation_oral_tx' => $sedation_oral_value,
                        'sedation_iv_tx' => $sedation_iv_value,
                        
                        'contrast_none_yn' => $contrast_none_yn,
                        'contrast_enteric_tx' => $contrast_enteric_value,
                        'contrast_iv_tx' => $contrast_iv_value,

                        'radioisotope_none_yn' => $radioisotope_none_yn,
                        'radioisotope_enteric_tx' => $radioisotope_enteric_value,
                        'radioisotope_iv_tx' => $radioisotope_iv_value,

                        'consent_received_kw' => $consent_received_kw,
                        
                        'current_workflow_state_cd' => $sCWFS,
                        'author_uid' => $nUID,
                        'created_dt' => $updated_dt,
                    ))
                    ->execute();
        }
        catch(\Exception $ex)
        {
            error_log('Failed to create raptor_ticket_exam_settings: ' . print_r($ex,TRUE));
            drupal_set_message('Failed to save exam information for this ticket because ' . $ex->getMessage(),'error');
            $bSuccess = FALSE;
        }
        
        if($bSuccess)
        {
            //Now write the dose information.
            $radiation_dose_tx = isset($myvalues['exam_ctdivol_radiation_dose_tx']) ? trim($myvalues['exam_ctdivol_radiation_dose_tx']) : '';
            $uom = isset($myvalues['exam_ctdivol_radiation_dose_uom_tx']) ? trim($myvalues['exam_ctdivol_radiation_dose_uom_tx']) : '';
            $dose_type_cd = isset($myvalues['exam_ctdivol_radiation_dose_type_cd']) ? trim($myvalues['exam_ctdivol_radiation_dose_type_cd']) : '';
            if($radiation_dose_tx != '')
            {
                $nPatientID = $patientDFN;
                $dose_type_cd = $dose_type_cd;
                $dose_source_cd = 'C';
                $data_provider = 'tech during exam';
                $dose_dt = $updated_dt;
                $this->writeRadiationDoseDetails($nSiteID, $nIEN, $nPatientID, $nUID
                        , $radiation_dose_tx,$uom
                        , $dose_dt, $dose_type_cd, $dose_source_cd, $data_provider, $updated_dt);                
            }
            $radiation_dose_tx = isset($myvalues['exam_dlp_radiation_dose_tx']) ? trim($myvalues['exam_dlp_radiation_dose_tx']) : '';
            $uom = isset($myvalues['exam_dlp_radiation_dose_uom_tx']) ? trim($myvalues['exam_dlp_radiation_dose_uom_tx']) : '';
            $dose_type_cd = isset($myvalues['exam_dlp_radiation_dose_type_cd']) ? trim($myvalues['exam_dlp_radiation_dose_type_cd']) : '';
            if($radiation_dose_tx != '')
            {
                $nPatientID = $patientDFN;
                $dose_type_cd = $dose_type_cd;
                $dose_source_cd = 'D';
                $data_provider = 'tech during exam';
                $dose_dt = $updated_dt;
                $this->writeRadiationDoseDetails($nSiteID, $nIEN, $nPatientID, $nUID
                        , $radiation_dose_tx,$uom
                        , $dose_dt, $dose_type_cd, $dose_source_cd, $data_provider, $updated_dt);                
            }
            $radiation_dose_tx = isset($myvalues['exam_other_radiation_dose_tx']) ? trim($myvalues['exam_other_radiation_dose_tx']) : '';
            $uom = isset($myvalues['exam_other_radiation_dose_uom_tx']) ? trim($myvalues['exam_other_radiation_dose_uom_tx']) : '';
            $dose_type_cd = isset($myvalues['exam_other_radiation_dose_type_cd']) ? trim($myvalues['exam_other_radiation_dose_type_cd']) : '';
            if($radiation_dose_tx != '')
            {
                $nPatientID = $patientDFN;
                $dose_type_cd = $dose_type_cd;
                $dose_source_cd = 'E';
                $data_provider = 'tech during exam';
                $dose_dt = $updated_dt;
                $this->writeRadiationDoseDetails($nSiteID, $nIEN, $nPatientID, $nUID
                        , $radiation_dose_tx,$uom
                        , $dose_dt, $dose_type_cd, $dose_source_cd, $data_provider, $updated_dt);                
            }
            
            $radiation_dose_tx = isset($myvalues['exam_radioisotope_radiation_dose_tx']) ? trim($myvalues['exam_radioisotope_radiation_dose_tx']) : '';
            $uom = isset($myvalues['exam_radioisotope_radiation_dose_uom_tx']) ? trim($myvalues['exam_radioisotope_radiation_dose_uom_tx']) : '';
            $dose_type_cd = isset($myvalues['exam_radioisotope_radiation_dose_type_cd']) ? trim($myvalues['exam_radioisotope_radiation_dose_type_cd']) : '';
            if($radiation_dose_tx != '')
            {
                $nPatientID = $patientDFN;
                $dose_type_cd = $dose_type_cd;
                $dose_source_cd = 'R';
                $data_provider = 'tech during exam';
                $dose_dt = $updated_dt;
                $this->writeRadiationDoseDetails($nSiteID, $nIEN, $nPatientID, $nUID
                        , $radiation_dose_tx,$uom
                        , $dose_dt, $dose_type_cd, $dose_source_cd, $data_provider, $updated_dt);                
            }
        }

        if($bSuccess)
        {
            //Create the raptor_ticket_exam_notes record now
            try
            {
                if(isset($myvalues['exam_notes_tx']) && trim($myvalues['exam_notes_tx']) !== '')
                {
                    $oInsert = db_insert('raptor_ticket_exam_notes')
                            ->fields(array(
                                'siteid' => $nSiteID,
                                'IEN' => $nIEN,
                                'notes_tx' => $myvalues['exam_notes_tx'],
                                'author_uid' => $nUID,
                                'created_dt' => $updated_dt,
                            ))
                            ->execute();
                }
            }
            catch(\Exception $e)
            {
                error_log('Failed to create raptor_ticket_exam_notes: ' . $e);
                form_set_error('exam_notes_tx','Failed to save notes for this ticket!');
                $bSuccess = FALSE;
            }
        }
        
        if($bSuccess && $sNewWFS != $sCWFS)
        {
            $this->changeTicketWorkflowStatus($nSiteID, $nIEN, $nUID, $sNewWFS, $sCWFS, $updated_dt);
        }
        return $bSuccess;
    }
    
    
    function writeRadiationDoseDetails($nSiteID, $nIEN, $nPatientID, $nUID
            , $radiation_dose_tx
            , $uom,$dose_dt, $dose_type_cd, $dose_source_cd, $data_provider
            , $updated_dt)
    {
        if($radiation_dose_tx != '')
        {
            $dose_values = explode(',', $radiation_dose_tx);
            $sequence_num = 0;
            foreach($dose_values as $dose)
            {
                $sequence_num++;
                try
                {
                    $oInsert = db_insert('raptor_ticket_exam_radiation_dose')
                            ->fields(array(
                                    'siteid' => $nSiteID,
                                    'IEN' => $nIEN,
                                    'patientid' => $nPatientID,

                                    'sequence_position' => $sequence_num,
                                    'dose' => $dose,
                                    'uom' => $uom,

                                    'dose_dt' => $dose_dt,
                                    'dose_type_cd' => $dose_type_cd,
                                    'dose_source_cd' => $dose_source_cd,
                                    'data_provider' => $data_provider,

                                    'author_uid' => $nUID,
                                    'created_dt' => $updated_dt,
                            ))
                            ->execute();
                } catch (\Exception $ex) {
                        error_log('Failed to create raptor_ticket_exam_radiation_dose: ' . print_r($ex,TRUE));
                        drupal_set_message('Failed to save exam dose information for this ticket because ' . $ex->getMessage(),'error');
                        $bSuccess = FALSE;
                }
            }
        }
    }

    
    public function saveAllQAFieldValues($nSiteID, $nIEN, $nUID, $sCWFS, $sNewWFS, $updated_dt, $myvalues)
    {
        $bSuccess = TRUE;
        //Create the raptor_ticket_qa_notes record now
        try
        {
            
            if(isset($myvalues['evaluations']) && is_array($myvalues['evaluations']))
            {
                //Write the evaluation answers.
                foreach($myvalues['evaluations'] as $shortname=>$aDetails)
                {
                    if(isset($aDetails['score']) && is_numeric($aDetails['score']))
                    {
                        $criteria_score = $aDetails['score'];
                        $criteria_version = $aDetails['version'];
                        $comment = $aDetails['comment'];
//        die('LOOK ABOUT TO WRITE "'.$shortname.'" with details '.print_r($aDetails,TRUE).'<br>..............<br>'.print_r($myvalues,TRUE));                
                        $oInsert = db_insert('raptor_ticket_qa_evaluation')
                            ->fields(array(
                                'siteid' => $nSiteID,
                                'IEN' => $nIEN,
                                'criteria_shortname' => $shortname,
                                'criteria_version' => $criteria_version,
                                'criteria_score' => $criteria_score,
                                'comment' => $comment,
                                'workflow_state' => $sCWFS,
                                'author_uid' => $nUID,
                                'evaluation_dt' => $updated_dt,
                            ))
                            ->execute();
                    }
                }
            }
        }
        catch(\Exception $e)
        {
                error_log('Failed to create raptor_ticket_qa_evaluation: ' . $e);
                form_set_error('qa_notes_tx','Failed to save QA notes for this ticket!'.$e);
                $bSuccess = FALSE;
        }
           
        if($bSuccess)
        {
            try
            {
                if(isset($myvalues['qa_notes_tx']) && trim($myvalues['qa_notes_tx']) !== '')
                {
                        $oInsert = db_insert('raptor_ticket_qa_notes')
                                        ->fields(array(
                                                'siteid' => $nSiteID,
                                                'IEN' => $nIEN,
                                                'notes_tx' => $myvalues['qa_notes_tx'],
                                                'author_uid' => $nUID,
                                                'created_dt' => $updated_dt,
                                        ))
                                        ->execute();
                }
            }
            catch(\Exception $e)
            {
                    error_log('Failed to create raptor_ticket_qa_notes: ' . $e);
                    form_set_error('qa_notes_tx','Failed to save notes for this ticket!');
                    $bSuccess = FALSE;
            }
        }
        
        if($bSuccess && $sNewWFS != $sCWFS)
        {
            $this->changeTicketWorkflowStatus($nSiteID, $nIEN, $nUID, $sNewWFS, $sCWFS, $updated_dt);
        }
        return $bSuccess;
    }


    public function saveAllInterpretationFieldValues($nSiteID, $nIEN, $nUID, $sCWFS, $sNewWFS, $updated_dt, $myvalues)
    {
        $bSuccess = TRUE;
        //Create the raptor_ticket_interpret_notes record now
        try
        {
                if(isset($myvalues['interpret_notes_tx']) && trim($myvalues['interpret_notes_tx']) !== '')
                {
                        $oInsert = db_insert('raptor_ticket_interpret_notes')
                                        ->fields(array(
                                                'siteid' => $nSiteID,
                                                'IEN' => $nIEN,
                                                'notes_tx' => $myvalues['interpret_notes_tx'],
                                                'author_uid' => $nUID,
                                                'created_dt' => $updated_dt,
                                        ))
                                        ->execute();
                }
        }
        catch(\Exception $e)
        {
                error_log('Failed to create raptor_ticket_interpret_notes: ' . $e);
                form_set_error('interpret_notes_tx','Failed to save notes for this ticket!');
                $bSuccess = FALSE;
        }
        
        if($bSuccess && $sNewWFS != $sCWFS)
        {
            $this->changeTicketWorkflowStatus($nSiteID, $nIEN, $nUID, $sNewWFS, $sCWFS, $updated_dt);
        }
        return $bSuccess;
    }
    
    /**
     * Alter the ticket workflow status
     * @param type $nSiteID
     * @param type $nIEN
     * @param type $nUID
     * @param type $sNewWFS
     * @param type $sCWFS
     * @param type $updated_dt
     * @return int
     */
    public function changeTicketWorkflowStatus($nSiteID, $nIEN, $nUID, $sNewWFS, $sCWFS, $updated_dt)
    {
        return $this->m_oTT->setTicketWorkflowState($nSiteID.'-'.$nIEN, $nUID, $sNewWFS, $sCWFS, $updated_dt);
    }

    /**
     * Fetch the state directly from the database.
     * @param type $nSiteID
     * @param type $nIEN
     */
    public function getCurrentWorkflowState($nSiteID, $nIEN)
    {
        return $this->m_oTT->getTicketWorkflowState($nSiteID.'-'.$nIEN);
    }
}
