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

module_load_include('php', 'raptor_glue', 'core/config');
//require_once ("config.php");
require_once ("MdwsUtils.php");
require_once ("TicketMetrics.php");
require_once ('RuntimeResultCache.php');

/**
 * This class provides methods to return filtered lists of rows for the worklist
 * and also a single worklist row when given a matching ID.
 *
 * @author SAN
 */
class WorklistData 
{
    private $m_oContext;
    private $m_oRuntimeResultCache;    //Cache results.
    
    //RAPTOR Worklist Field Index
    const WLIDX_TRACKINGID = 0;
    const WLIDX_PATIENTID = 1;
    const WLIDX_PATIENTNAME = 2;
    const WLIDX_DATETIMEDESIRED = 3;
    const WLIDX_DATEORDERED = 4;
    const WLIDX_MODALITY = 5;
    const WLIDX_STUDY = 6;
    const WLIDX_URGENCY = 7;
    const WLIDX_TRANSPORT = 8;
    const WLIDX_PATIENTCATEGORYLOCATION = 9;
    const WLIDX_ANATOMYIMAGESUBSPEC = 10;
    const WLIDX_WORKFLOWSTATUS = 11;
    const WLIDX_ASSIGNEDUSER = 12;
    const WLIDX_ORDERSTATUS = 13;
    const WLIDX_EDITINGUSER = 14;
    const WLIDX_SCHEDINFO = 15;
    const WLIDX_CPRSCODE = 16;
    const WLIDX_IMAGETYPE = 17;
    const WLIDX_RANKSCORE = 18;
    const WLIDX_COUNTPENDINGORDERSSAMEPATIENT = 19;    
    const WLIDX_MAPPENDINGORDERSSAMEPATIENT = 20;    
    const WLIDX_EXAMLOCATION = 21;
    const WLIDX_REQUESTINGPHYSICIAN = 22;
    const WLIDX_REASONFORSTUDY = 23;
    
    //Worklist Vista Field Order
    const WLVFO_PatientID             = 1;
    const WLVFO_PatientName           = 2;
    const WLVFO_ExamCategory          = 3;
    const WLVFO_RequestingPhysician   = 4;
    const WLVFO_OrderedDate           = 5;
    const WLVFO_Procedure             = 6;
    const WLVFO_ImageType             = 7;
    const WLVFO_ExamLocation          = 8;
    const WLVFO_Urgency               = 9;
    const WLVFO_Nature                = 10;
    const WLVFO_Transport             = 11;
    const WLVFO_DesiredDate           = 12;
    
    
    function __construct($oContext)
    {
        $this->m_oContext = $oContext;
        $this->m_oRuntimeResultCache = \raptor\RuntimeResultCache::getInstance($this->m_oContext,'WorklistData');
    }

    /**
     * Convert the code into a word
     * @param type $sWMODE
     * @return string the word associated with the code
     */
    static function getWorklistModeName($sWMODE)
    {
        if($sWMODE=='P'){
            $sName="Protocol";
        } elseif ($sWMODE=='E'){
            $sName="Examination";
        } elseif ($sWMODE=='I'){
            $sName="Interpretation";
        } elseif ($sWMODE=='Q'){
            $sName="QA";
        } else {
            die("Invalid WorklistMode='$sWMODE'!!!");
        }
        return $sName;
    }
    

    // simply create a "dictionary" organized by key field IEN
    private function parseSqlTicketTracking($sqlResult) {
        $result = array();
        
        if ($sqlResult->rowCount() === 0) {
            return $result;
        }
        
        foreach ($sqlResult as $row) {
            $key = $row->IEN;
            $result[$key] = $row;
        }

        return $result;
    }
    
    private function getWorklistTrackingFromSQL() 
    {
        
        $sql = "SELECT * FROM raptor_ticket_tracking";
        $sqlResult = db_query($sql);
        $ticketTrackingResult = $this->parseSqlTicketTracking($sqlResult);
        
        //$sql = "SELECT * FROM raptor_ticket_collaboration WHERE active_yn";
        $sql = "SELECT c.IEN, c.collaborator_uid, c.requester_notes_tx, c.requested_dt, u.username, u.usernametitle, u.firstname, u.lastname, u.suffix FROM raptor_ticket_collaboration c left join raptor_user_profile u on c.collaborator_uid=u.uid WHERE active_yn";
        $sqlResult = db_query($sql);
        $ticketCollaborationResult = $this->parseSqlTicketTracking($sqlResult);
        
        $sql = "SELECT * FROM raptor_schedule_track";
        $sqlResult = db_query($sql);
        $scheduleTrackResult = $this->parseSqlTicketTracking($sqlResult);
        
        return array(
            "raptor_ticket_tracking" => $ticketTrackingResult,
            "raptor_ticket_collaboration" => $ticketCollaborationResult,
            "raptor_schedule_track" => $scheduleTrackResult);
    }

    /**
     * Gets each row of the worklist.
     * @return array with following keys ...
     *  'all_rows'              = array with integer index of all the worklist rows
     *  'pending_orders_map'   = array with patient id key and count of orders for that patient
     *  'matching_offset'       = offset of the row matching the match IEN
     */
    private function parseWorklist($MDWSResponse, $ticketTrackingDict, $match_IEN=NULL)
    {
        $sThisResultName = 'parseWorklist';
        $aCachedResult = $this->m_oRuntimeResultCache->checkCache($sThisResultName);
        if($aCachedResult !== NULL)
        {
            //Found it in the cache!
            if($match_IEN !== NULL)
            {
                //Make sure this has expected pointer.
                if(isset($aCachedResult['matching_offset']) && $aCachedResult['matching_offset'] > '')
                {
                    $offset = $aCachedResult['matching_offset'];
                    $all_rows = $aCachedResult['all_rows'];
                    $row = $all_rows[$offset];
                    $found_IEN = $row[WorklistData::WLIDX_TRACKINGID];
                    if($found_IEN !== $match_IEN)
                    {
                        //The cached result needs update before we can use it for this call.
                        $aCachedResult['matching_offset'] = NULL;
                        for ($i=0; $i<count($all_rows); $i++)
                        {
                            $row = $all_rows[$i];
                            if($match_IEN == $row[WorklistData::WLIDX_TRACKINGID])
                            {
                                //Found it!
                                $aCachedResult['matching_offset'] = $i;
                                break;
                            }
                        }
                    }
                }
            }
            return $aCachedResult;
        }
        $aPatientPendingOrderCount = array();
        $aPatientPendingOrderMap = array();
        $nOffsetMatchIEN = NULL;
        
        $ls=0;
                
        $oUserInfo = $this->m_oContext->getUserInfo();
        $worklist = array();
        $numOrders = isset($MDWSResponse->ddrListerResult) ? $MDWSResponse->ddrListerResult->count : 0;
        if($numOrders == 0)
        {
            if(is_object($MDWSResponse))
            {
                if(isset($MDWSResponse->ddrListerResult))
                {
                    $showinfo = print_r($MDWSResponse->ddrListerResult,TRUE);
                } else {
                    $showinfo = '(No DDRLister results)';
                }
            } else {
                $showinfo = '(Non-object DDRLister Result)';
            }
            error_log("DID NOT FIND ANY DATA IN MDWS!  MDWSResponse Details START...\n" 
                    . print_r($MDWSResponse, TRUE) 
                    . "\n...MDWSResponse Details END!\nMDWSResponse->ddrListerResult Details Start...\n" 
                    . $showinfo . "\nMDWSResponse->ddrListerResult Details END...");
            return false;
        }
        $strings = isset($MDWSResponse->ddrListerResult->text->string) ? $MDWSResponse->ddrListerResult->text->string : array();
        //print_r($strings);
                
        $exploded = array();
        $t = array();
        $nFound=0;
        
        $ticketTrackingRslt = $ticketTrackingDict["raptor_ticket_tracking"];
        $ticketCollabRslt = $ticketTrackingDict["raptor_ticket_collaboration"];
        $scheduleTrackRslt = $ticketTrackingDict["raptor_schedule_track"];
        
        for ($i=0; $i<$numOrders; $i++)
        {
            $exploded = explode("^", $strings[$i]);
            
            $ienKey = $exploded[0];
            $sqlTicketTrackRow = array_key_exists($ienKey, $ticketTrackingRslt) ? $ticketTrackingRslt[$ienKey] : null; // use IEN from MDWS results as key
            $sqlTicketCollaborationRow = array_key_exists($ienKey, $ticketCollabRslt) ? $ticketCollabRslt[$ienKey] : null; // use IEN from MDWS results as key
            $sqlScheduleTrackRow = array_key_exists($ienKey, $scheduleTrackRslt) ? $scheduleTrackRslt[$ienKey] : null; // use IEN from MDWS results as key

            $patientID = $exploded[WorklistData::WLVFO_PatientID];
            $t[WorklistData::WLIDX_TRACKINGID]  = $exploded[0];
            $t[WorklistData::WLIDX_PATIENTID]   = $patientID; 
            $t[WorklistData::WLIDX_PATIENTNAME] = $this->formatPatientName($exploded[WorklistData::WLVFO_PatientName]);
            $t[WorklistData::WLIDX_DATETIMEDESIRED] = $exploded[WorklistData::WLVFO_DesiredDate];
            
            if($exploded[WorklistData::WLVFO_OrderedDate] !== '' && ($last = strrpos($exploded[WorklistData::WLVFO_OrderedDate], ':')) !== FALSE)
            {
                //Remove the seconds from the time.
                $dateordered = substr($exploded[WorklistData::WLVFO_OrderedDate], 0, $last);
            } else {
                //Assume there is no time portion.
                $dateordered = $exploded[WorklistData::WLVFO_OrderedDate];
            }
            $t[WorklistData::WLIDX_DATEORDERED]     = $dateordered;
            
            
            $t[WorklistData::WLIDX_STUDY]       = $exploded[WorklistData::WLVFO_Procedure];
            $t[WorklistData::WLIDX_URGENCY]     = $exploded[WorklistData::WLVFO_Urgency]; 
            switch(trim($exploded[WorklistData::WLVFO_Transport]))
            {
                case "a":
                    $t[WorklistData::WLIDX_TRANSPORT] = "AMBULATORY";
                    break;
                case "p":
                    $t[WorklistData::WLIDX_TRANSPORT] = "PORTABLE";
                    break;
                case "s":
                    $t[WorklistData::WLIDX_TRANSPORT] = "STRETCHER";
                    break;
                case "w":
                    $t[WorklistData::WLIDX_TRANSPORT] = "WHEEL CHAIR";
                    break;
                default:
                    $t[WorklistData::WLIDX_TRANSPORT] = " ";
                    break;
            }
            $t[WorklistData::WLIDX_PATIENTCATEGORYLOCATION]     = $exploded[WorklistData::WLVFO_ExamCategory];
            $t[WorklistData::WLIDX_ANATOMYIMAGESUBSPEC]         = 'TODO ANATOMY';   //Placeholder for anatomy keywords
            $t[WorklistData::WLIDX_WORKFLOWSTATUS]              = (isset($sqlTicketTrackRow) ? $sqlTicketTrackRow->workflow_state : "AC"); // default to "AC"

            //Only show an assignment if ticket has not yet moved downstream in the workflow.
            if($t[WorklistData::WLIDX_WORKFLOWSTATUS] == 'CO' || $t[WorklistData::WLIDX_WORKFLOWSTATUS] == 'RV')
            {
                $t[WorklistData::WLIDX_ASSIGNEDUSER] = (isset($sqlTicketCollaborationRow) ? array(
                                                          'uid'=>$sqlTicketCollaborationRow->collaborator_uid
                                                        , 'requester_notes_tx'=>$sqlTicketCollaborationRow->requester_notes_tx
                                                        , 'requested_dt'=>$sqlTicketCollaborationRow->requested_dt
                                                        , 'username'=>$sqlTicketCollaborationRow->username
                                                        , 'fullname'=>trim($sqlTicketCollaborationRow->usernametitle . ' ' .$sqlTicketCollaborationRow->firstname
                                                                . ' ' .$sqlTicketCollaborationRow->lastname. ' ' .$sqlTicketCollaborationRow->suffix )
                                                    ) : NULL);    
            } else {
                $t[WorklistData::WLIDX_ASSIGNEDUSER] = '';
            }

            $t[WorklistData::WLIDX_ORDERSTATUS]  = 'TODO ORDER STATUS';   //Placeholder for Order Status
                    
            $t[WorklistData::WLIDX_EDITINGUSER]      = '';   //Placeholder for UID of user that is currently editing the record, if any. (check local database)

            $t[WorklistData::WLIDX_EXAMLOCATION] = $exploded[WorklistData::WLVFO_ExamLocation];
            $t[WorklistData::WLIDX_REQUESTINGPHYSICIAN] = $exploded[WorklistData::WLVFO_RequestingPhysician];
            switch (trim($exploded[WorklistData::WLVFO_Nature]))
            {
                case 'w' :
                    $t[WorklistData::WLIDX_REASONFORSTUDY] = "WRITTEN";
                    break;
                case 'v' :
                    $t[WorklistData::WLIDX_REASONFORSTUDY] = "VERBAL";
                    break;
                case 'p' :
                    $t[WorklistData::WLIDX_REASONFORSTUDY] = "TELEPHONED";
                    break;
                case 's' :
                    $t[WorklistData::WLIDX_REASONFORSTUDY] = "SERVICE CORRECTION";
                    break;
                case 'i' :
                    $t[WorklistData::WLIDX_REASONFORSTUDY] = "POLICY";
                    break;
                case 'e' :
                    $t[WorklistData::WLIDX_REASONFORSTUDY] = "PHYSICIAN ENTERED";
                    break;
                default :
                    $t[WorklistData::WLIDX_REASONFORSTUDY] = "NOT ENTERED";
                    break;
            }
                    
            // Pull schedule from raptor_schedule_track
            if($sqlScheduleTrackRow != null)
            {
                //If a record exists, then there is something to see.
                $showText = '';
                if(isset($sqlScheduleTrackRow->scheduled_dt))
                {
                    $phpdate = strtotime( $sqlScheduleTrackRow->scheduled_dt );
                    $sdt = date( 'Y-m-d H:i', $phpdate ); //Remove the seconds
                    if(isset($sqlScheduleTrackRow->confirmed_by_patient_dt))
                    {
                        if($showText > '')
                        {
                           $showText .= '<br>'; 
                        }
                        $showText .= 'Confirmed '.$sqlScheduleTrackRow->confirmed_by_patient_dt; 
                    }
                    if($showText > '')
                    {
                       $showText .= '<br>'; 
                    }
                    $showText .= 'For '. $sdt ;//$sqlScheduleTrackRow->scheduled_dt; 
                    if(isset($sqlScheduleTrackRow->location_tx))
                    {
                        if($showText > '')
                        {
                           $showText .= '<br>'; 
                        }
                        $showText .= 'In ' . $sqlScheduleTrackRow->location_tx; 
                    }
                }
                if(isset($sqlScheduleTrackRow->canceled_dt))
                {
                    //If we are here, clear everything before.
                    $showText = 'Canceled '.$sqlScheduleTrackRow->canceled_dt; 
                }
                if(trim($sqlScheduleTrackRow->notes_tx) > '')
                {
                    if($showText > '')
                    {
                       $showText .= '<br>'; 
                    }
                    $showText .= 'See Notes...'; 
                }
                if($showText == '')
                {
                    //Indicate there is someting to see in the form.
                    $showText = 'See details...';
                }
                $t[WorklistData::WLIDX_SCHEDINFO] = array(
                    'EventDT' => $sqlScheduleTrackRow->scheduled_dt,
                    'LocationTx' => $sqlScheduleTrackRow->location_tx,
                    'ConfirmedDT' => $sqlScheduleTrackRow->confirmed_by_patient_dt,
                    'CanceledDT' => $sqlScheduleTrackRow->canceled_dt,
                    'ShowTx' => $showText
                );
                print_r($sqlScheduleTrackRow, TRUE);
            } else {
                //No record exists yet.
                $t[WorklistData::WLIDX_SCHEDINFO] = array(
                    'EventDT' => NULL,
                    'LocationTx' => NULL,
                    'ConfirmedDT' => NULL,
                    'CanceledDT' => NULL,
                    'ShowTx' => 'Unknown'
                );
            }

            $t[WorklistData::WLIDX_CPRSCODE]    = '';   //Placeholder for the CPRS code associated with this ticket
            $t[WorklistData::WLIDX_IMAGETYPE]   = $exploded[WorklistData::WLVFO_ImageType];   //Placeholder for Imaging Type - file 75.1, field 3

            $t[WorklistData::WLIDX_COUNTPENDINGORDERSSAMEPATIENT] = -1;  //Important that we allocate something here, will replace later.
            
            $modality = $this->getImpliedModality($t[WorklistData::WLIDX_STUDY]);   //20140603
            $t[WorklistData::WLIDX_MODALITY] = 'Unknown';
            if($modality != '')    //Do not return the row if we cannot determine the modality.  TODO --- Replace this approach!!!!
            {
                //Count this order as pending for the patient?
                if($t[WorklistData::WLIDX_WORKFLOWSTATUS] == 'AC'
                        || $t[WorklistData::WLIDX_WORKFLOWSTATUS] == 'CO'
                        || $t[WorklistData::WLIDX_WORKFLOWSTATUS] == 'RV')
                {
                    $aPatientPendingOrderMap[$patientID][$ienKey] 
                            = array($ienKey
                                ,$modality
                                ,$t[WorklistData::WLIDX_STUDY]);
                    if(isset($aPatientPendingOrderCount[$patientID]))
                    {
                        $aPatientPendingOrderCount[$patientID] +=  1; 
                    } else {
                        $aPatientPendingOrderCount[$patientID] =  1; 
                    }
                }
                
                $nFound++;
                $offset = $nFound;
                $t[WorklistData::WLIDX_MODALITY] = $modality;

                //Compute the score for this row.
                $t[WorklistData::WLIDX_RANKSCORE] = TicketMetrics::getTicketRelevance($oUserInfo, $t);
                
                //Add this row to the worklist because modality not blank.
                $worklist[$offset] = $t;    
                if($ienKey == $match_IEN)
                {
                    $nOffsetMatchIEN = $offset;
                }
            }

        }
        for($i=0;$i<count($worklist);$i++)
        {
            $t = &$worklist[$i];
            if(is_array($t))
            {
                //Yes, this is a real row.
                $patientID = $t[WorklistData::WLIDX_PATIENTID];
                if(isset($aPatientPendingOrderMap[$patientID]))
                {
                    $t[WorklistData::WLIDX_MAPPENDINGORDERSSAMEPATIENT] = $aPatientPendingOrderMap[$patientID];
                    $t[WorklistData::WLIDX_COUNTPENDINGORDERSSAMEPATIENT] = $aPatientPendingOrderCount[$patientID];
                } else {
                    //Found no pending orders for this IEN
                    $t[WorklistData::WLIDX_MAPPENDINGORDERSSAMEPATIENT] = array();;
                    $t[WorklistData::WLIDX_COUNTPENDINGORDERSSAMEPATIENT] = 0;
                }
            }
        }

        //Populate the array of results
        $result = array('all_rows'=>&$worklist
                        ,'pending_orders_map'=>&$aPatientPendingOrderMap
                        ,'matching_offset'=>$nOffsetMatchIEN);
        
        $this->m_oRuntimeResultCache->addToCache($sThisResultName, $result);
        return $result;
    }
    
    /**
     * @return string of fields to get from VISTA
     */
    private function getWorklistVistaFieldArgumentString()
    {
        $requestedFields = array();
        $requestedFields[0] = null;
        $requestedFields[WorklistData::WLVFO_PatientID] = ".01I";       
        $requestedFields[WorklistData::WLVFO_PatientName] = ".01";
        $requestedFields[WorklistData::WLVFO_ExamCategory] = "4";
        $requestedFields[WorklistData::WLVFO_RequestingPhysician] = "14";
        $requestedFields[WorklistData::WLVFO_OrderedDate] = "16";
        $requestedFields[WorklistData::WLVFO_Procedure] = "2";
        $requestedFields[WorklistData::WLVFO_ImageType] = "3";
        $requestedFields[WorklistData::WLVFO_ExamLocation] = "20";
        $requestedFields[WorklistData::WLVFO_Urgency] = "6";
        $requestedFields[WorklistData::WLVFO_Nature] = "26";
        $requestedFields[WorklistData::WLVFO_Transport] = "19";
        $requestedFields[WorklistData::WLVFO_DesiredDate] = "21";
        
        // Compose argument string by concatenating field codes into semicolon-delimited list
        $argument = "";
        foreach($requestedFields as $rf)
        {
            if ($argument != "") $argument .= ";";
            $argument .= $rf;
        }
        
        return $argument;
    }

    /**
     * @description Return result of web service call to MDWS, web method QueryService.ddrLister
     */
    private function getWorklistFromMDWS()
    {
        //error_log('DEBUG called getWorklistFromMDWS start');
        $sThisResultName = 'getWorklistFromMDWS';
        $aCachedResult = $this->m_oRuntimeResultCache->checkCache($sThisResultName);
        if($aCachedResult !== NULL)
        {
            //Found it in the cache!
            return $aCachedResult;
        }
        
        $result = NULL;
        try{
            if(!isset($this->m_oContext))
            {
                throw new \Exception('getWorklistFromMDWS failed because Context object is not set!');
            }
            $result = $this->m_oContext->getMdwsClient()->makeQuery("ddrLister", array(
                'file'=>'75.1', 
                'iens'=>'',     //Only for sub files
                'fields'=>$this->getWorklistVistaFieldArgumentString(), 
                'flags'=>'PB',      //P=PACKED format, B=Back
                'maxrex'=>'1780',   //20140926 Known issue with RPC if this number is too big need to look into fix
                'from'=>'',         //For pagination provide smallest IEN as startign point for new query
                'part'=>'',         //ignore
                'xref'=>'#',        //Leave as #
                'screen'=>'', //I ($P(^(0),U,5)=5)|($P(^(0),U,5)=6)',   //Server side filtering but APPLIED TO EACH RECORD ONE BY ONE VERY SLOW NO FILTERING BEFOREHAND
                'identifier'=>''    //Mumps code for filtering etc
                ));
        } catch (\Exception $ex) {
            $msg = 'Trouble getting worklist because '.$ex;
            error_log($msg);
            throw $ex;
        }
        //error_log('DEBUG called getWorklistFromMDWS done with result >>>'.print_r($result,TRUE));
        $this->m_oRuntimeResultCache->addToCache($sThisResultName, $result);
        return $result;
    }
    
    private function getWorklistItemFromMDWS($sTrackingID)
    {
        //error_log('DEBUG called getWorklistItemFromMDWS start with ['.$sTrackingID.']');

        //Get the IEN from the tracking ID
        $aParts = (explode('-',$sTrackingID));
        if(count($aParts) == 2)
        {
            $nIEN = $aParts[1]; //siteid-IEN
        } else 
        if(count($aParts) == 1)     
        {
            $nIEN = $aParts[0]; //Just IEN
        } else {
            $sMsg = 'Did NOT recognize format of tracking id ['.$sTrackingID.'] expected SiteID-IEN format!';
            error_log($sMsg);
            throw new \Exception($sMsg);
        }
        
        $aResult = MdwsUtils::parseDdrGetsEntry($this->m_oContext->getMdwsClient()->makeQuery("ddrGetsEntry", array(
            'file'=>'75.1', 
            'iens'=>($nIEN.','),
            'flds'=>'*', 
            'flags'=>'IEN'
            )));

        //error_log('DEBUG called getWorklistItemFromMDWS done with result >>>'.print_r($aResult,TRUE));
        return $aResult;
    }
    
    public static function formatSSN($digits)
    {
        if($digits != NULL && strlen($digits) == 9)
        {
            return $digits[0] . $digits[1] . $digits[2] . '-' . $digits[3] . $digits[4] . '-' . $digits[5] . $digits[6] . $digits[7];
        }
        return $digits;
    }
    

    /**
     * Derive the modality from the text if we can.
     * @param type $sProcedureText
     * @return modality abbreviation if derivable, else empty string.
     */
    private function getImpliedModality($sProcedureText)
    {
        //Make sure we only show rows that start with modality. 20140320
        $modality = strtoupper(substr($sProcedureText,0,2));
        $real_modality_pos = strpos("MR CT NM", $modality);
        if($real_modality_pos !== FALSE)
        {
            $okpos = TRUE;
        } else {
            //Temporary hardcoded logic for modality detection
            $okpos = strpos($sProcedureText, 'NUC');
            if($okpos !== FALSE)				
            {
                $modality = 'NM';
            } else {
                $okpos = strpos($sProcedureText, 'MAGNETIC');
                if($okpos !== FALSE)				
                {
                    $modality = 'MR';
                } else {
                    $okpos = (strpos($sProcedureText, 'FLUORO') !== FALSE || strpos($sProcedureText, 'ARTHROGRAM'));
                    if($okpos !== FALSE)				
                    {
                        $modality = 'FL';
                    } else {
                        $okpos = (strpos($sProcedureText, 'ECHOGRAM') !== FALSE || strpos($sProcedureText, 'ULTRASOUND'));
                        if($okpos !== FALSE)				
                        {
                            $modality = 'US';
                        } else {
                            $okpos = strpos($sProcedureText, 'BONE');
                            if($okpos !== FALSE)				
                            {
                                $modality = 'NM';
                            } else {
                                $modality = ''; //Leave modality blank we do not know what it is.
                            }
                        }
                    }
                }
            }
        }
        return $modality;
    }
    
    
    /**
     * @description return standard-formatted patient's name (lAST_NAME, FIRST_NAME), based on the specified full name
     */
    private function formatPatientName($fullName)
    {
        $nameArray = explode(',', $fullName);
        return $nameArray[0].", ".$nameArray[1];
    }
    
    /**
     * Get all the worklist rows for the provided context
     * @param type $oContext
     * @return type array of rows for the worklist page
     */
    public function getWorklistRows()   //$oContext)
    {
        
        $sThisResultName = 'getWorklistRows';
        $aCachedResult = $this->m_oRuntimeResultCache->checkCache($sThisResultName);
        if($aCachedResult !== null)
        {
            //Found it in the cache!
            return $aCachedResult;
        }
        
        $mdwsResponse = $this->getWorklistFromMDWS();
        $sqlResponse = $this->getWorklistTrackingFromSQL();
        
        $currentTrackingID = $this->m_oContext->getSelectedTrackingID();
        $parsedWorklist = $this->parseWorklist($mdwsResponse, $sqlResponse, $currentTrackingID);

        $dataRows = $parsedWorklist['all_rows'];
        $pending_orders_map = $parsedWorklist['pending_orders_map'];
        $matching_offset = $parsedWorklist['matching_offset'];
        
        $aResult = array('Pages'=>1
                        ,'Page'=>1
                        ,'RowsPerPage'=>9999
                        ,'DataRows'=>$dataRows
                        ,'matching_offset' => $matching_offset
                        ,'pending_orders_map' => $pending_orders_map
            );	
        $this->m_oRuntimeResultCache->addToCache($sThisResultName, $aResult);
        return $aResult;
    }
  
    public function getPendingOrdersMap()
    {
        $aResult = $this->getWorklistRows();
        return $aResult['pending_orders_map'];
    }
    
    public function getDashboardMap($override_tracking_id=NULL)
    {
        if($override_tracking_id !== NULL)
        {
            //TODO -- Change this side-effect so override does NOT alter the context for the session!!!!
            $this->m_oContext->setSelectedTrackingID($override_tracking_id);        
        }
        
        $aResult = $this->getWorklistRows();
        $offset = $aResult['matching_offset'];
        $all_rows = $aResult['DataRows'];
        $row = $all_rows[$offset];

        $currentTrackingID = $this->m_oContext->getSelectedTrackingID();
        if(!isset($row[WorklistData::WLIDX_TRACKINGID]))
        {
            throw new \Exception('Expected to get dashboard for trackingID=['.$currentTrackingID.'] but did not!');
        }
        if($currentTrackingID != $row[WorklistData::WLIDX_TRACKINGID])
        {
            throw new \Exception('Expected to get dashboard for trackingID=['.$currentTrackingID.'] but got data for ['.$row[WorklistData::WLIDX_TRACKINGID].'] instead!');
        }
        
        $siteid = $this->m_oContext->getSiteID();
        $tid = $row[WorklistData::WLIDX_TRACKINGID];
        $pid = $row[WorklistData::WLIDX_PATIENTID];
        $oPatientData = $this->getPatient($pid);
        if($oPatientData == NULL)
        {
            throw new \Exception('Did not get patient data for trackingID=['.$currentTrackingID.']');
        }
        
        // use DDR GETS ENTRY to fetch CLINICAL Hx WP field
        $worklistItemDict = $this->getWorklistItemFromMDWS($tid);

        $t['Tracking ID']       = $siteid.'-'.$tid;
        $t['Procedure']         = $row[WorklistData::WLIDX_STUDY];
        $t['Modality']          = $row[WorklistData::WLIDX_MODALITY];

        $t['ExamCategory']      = $row[WorklistData::WLIDX_PATIENTCATEGORYLOCATION];
        $t['PatientLocation']   = $row[WorklistData::WLIDX_EXAMLOCATION];       
        $t['RequestedBy']       = $row[WorklistData::WLIDX_REQUESTINGPHYSICIAN];
        
        $aSchedInfo = $row[WorklistData::WLIDX_SCHEDINFO];
        $t['SchedInfo']         = $aSchedInfo;
        $t['RequestedDate']     = $row[WorklistData::WLIDX_DATEORDERED]; 
        $t['DesiredDate']       = $row[WorklistData::WLIDX_DATETIMEDESIRED]; 
        $t['ScheduledDate']     = $aSchedInfo['EventDT'];
            
        $t['PatientCategory']   = $row[WorklistData::WLIDX_PATIENTCATEGORYLOCATION];
        $t['ReasonForStudy']    = $row[WorklistData::WLIDX_REASONFORSTUDY];
        
        $t['ClinicalHistory']   = trim((isset($worklistItemDict["400"]) ? $worklistItemDict["400"] : '') );
        $t['PatientID']         = $pid;
        $t['PatientSSN']        = WorklistData::formatSSN($oPatientData['ssn']);
        $t['Urgency']           = $row[WorklistData::WLIDX_URGENCY];
        $t['Transport']         = $row[WorklistData::WLIDX_TRANSPORT];
        $t['PatientName']       = $row[WorklistData::WLIDX_PATIENTNAME];
        $t['PatientAge']        = $oPatientData['age'];
        $t['PatientDOB']        = $oPatientData['dob'];
        $t['PatientEthnicity']  = $oPatientData['ethnicity'];
        $t['PatientGender']     = $oPatientData['gender'];
        $t['ImageType']         = $row[WorklistData::WLIDX_IMAGETYPE];
        $t['mpiPid']            = $oPatientData['mpiPid'];
        $t['mpiChecksum']       = $oPatientData['mpiChecksum'];
        $t['CountPendingOrders']   = $row[WorklistData::WLIDX_COUNTPENDINGORDERSSAMEPATIENT];
        $t['MapPendingOrders']     = $row[WorklistData::WLIDX_MAPPENDINGORDERSSAMEPATIENT];
        return $t;
    }
    
    /**
     * @description Call EMR web service method "select" passing as an argument PatientID stored in context
     * @return Array containing patient information 
     * This is an old function but appears to be in use; moved out of Context class on 5/21/2014
     */
    public function getPatient($pid)
    {
        if(!isset($pid) || $pid == null || $pid == '')
        {
            error_log('Cannot get patient if pid is not provided!');
            return null;
        }
        
        //$serviceResponse = $this->getEMRService()->select(array('DFN'=>$pid == null ? $this->getPatientID() : $pid));
        $serviceResponse = $this->m_oContext->getMdwsClient()->makeQuery("select", array('DFN'=>$pid));
        //drupal_set_message('LOOK DFN RESULT>>>>' . print_r($serviceResponse, TRUE));

        
        $result = array();
        if(!isset($serviceResponse->selectResult))
                return $result;
        
        $RptTO = $serviceResponse->selectResult;
        if(isset($RptTO->fault))
        { 
            return $result;
        }
        $result['patientName'] = isset($RptTO->name) ? $RptTO->name : " ";
        $result['ssn'] = isset($RptTO->ssn) ? $RptTO->ssn : " ";
        $result['gender'] = isset($RptTO->gender) ? $RptTO->gender : " ";
        $result['dob'] = isset($RptTO->dob) ? date("m/d/Y", strtotime($RptTO->dob)) : " ";
        $result['ethnicity'] = isset($RptTO->ethnicity) ? $RptTO->ethnicity : " ";
        $result['age'] = isset($RptTO->age) ? $RptTO->age : " ";
        $result['maritalStatus'] = isset($RptTO->maritalStatus) ? $RptTO->maritalStatus : " ";
        $result['age'] = isset($RptTO->age) ? $RptTO->age : " ";
        $result['mpiPid'] = isset($RptTO->mpiPid) ? $RptTO->mpiPid : " ";
        $result['mpiChecksum'] = isset($RptTO->mpiChecksum) ? $RptTO->mpiChecksum : " ";
        $result['localPid'] = isset($RptTO->localPid) ? $RptTO->localPid : " ";
        $result['sitePids'] = isset($RptTO->sitePids) ? $RptTO->sitePids : " ";
        $result['vendorPid'] = isset($RptTO->vendorPid) ? $RptTO->vendorPid : " ";
        if(isset($RptTO->location))
        {
            $aLocation = $RptTO->location;
            $room = "Room: ";
            $room .=isset($aLocation->room)? $aLocation->room : " ";
            $bed =  "Bed: ";
            $bed .= (isset($aLocation->bed) ? $aLocation->bed : " " );
            $result['location'] = $room." / ".$bed;
        }
        else
        {
            $result['location'] = "Room:? / Bed:? ";
        }
        $result['cwad'] = isset($RptTO->cwad) ? $RptTO->cwad : " ";
        $result['restricted'] = isset($RptTO->restricted) ? $RptTO->restricted : " ";
        
        $result['admitTimestamp'] = isset($RptTO->admitTimestamp) ? date("m/d/Y h:i a", strtotime($RptTO->admitTimestamp)) : " ";
        
        $result['serviceConnected'] = isset($RptTO->serviceConnected) ? $RptTO->serviceConnected : " ";
        $result['scPercent'] = isset($RptTO->scPercent) ? $RptTO->scPercent : " ";
        $result['inpatient'] = isset($RptTO->inpatient) ? $RptTO->inpatient : " ";
        $result['deceasedDate'] = isset($RptTO->deceasedDate) ? $RptTO->deceasedDate : " ";
        $result['confidentiality'] = isset($RptTO->confidentiality) ? $RptTO->confidentiality : " ";
        $result['needsMeansTest'] = isset($RptTO->needsMeansTest) ? $RptTO->needsMeansTest : " ";
        $result['patientFlags'] = isset($RptTO->patientFlags) ? $RptTO->patientFlags : " ";
        $result['cmorSiteId'] = isset($RptTO->cmorSiteId) ? $RptTO->cmorSiteId : " ";
        $result['activeInsurance'] = isset($RptTO->activeInsurance) ? $RptTO->activeInsurance : " ";
        $result['isTestPatient'] = isset($RptTO->isTestPatient) ? $RptTO->isTestPatient : " ";
        $result['currentMeansStatus'] = isset($RptTO->currentMeansStatus) ? $RptTO->currentMeansStatus : " ";
        $result['hasInsurance'] = isset($RptTO->hasInsurance) ? $RptTO->hasInsurance : " ";
        $result['preferredFacility'] = isset($RptTO->preferredFacility) ? $RptTO->preferredFacility : " ";
        $result['patientType'] = isset($RptTO->patientType) ? $RptTO->patientType : " ";
        $result['isVeteran'] = isset($RptTO->isVeteran) ? $RptTO->isVeteran : " ";
        $result['isLocallyAssignedMpiPid'] = isset($RptTO->isLocallyAssignedMpiPid) ? $RptTO->isLocallyAssignedMpiPid : " ";
        $result['sites'] = isset($RptTO->sites) ? $RptTO->sites : " ";
        $result['teamID'] = isset($RptTO->teamID) ? $RptTO->teamID : " ";
        $result['teamName'] = isset($RptTO->name) ? $RptTO->name : "Unknown";
        $result['teamPcpName'] = isset($RptTO->pcpName) ? $RptTO->pcpName : "Unknown";
        $result['teamAttendingName'] = isset($RptTO->attendingName) ? $RptTO->attendingName : "Unknown";
        $result['mpiPid'] = isset($RptTO->mpiPid) ? $RptTO->mpiPid : "Unknown";
        $result['mpiChecksum'] = isset($RptTO->mpiChecksum) ? $RptTO->mpiChecksum : "Unknown";

        return $result;
    }
}

