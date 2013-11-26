<?php
/** 
 * Filters Mail Messages based on user specified parameters and does some action on matched ones. Something similar to that of Gmail Filters
 *
 * Based on the amazing PHP-IMAP class by Barbushin Sergey - https://github.com/barbushin/php-imap
 * 
 * @author Ayman Bedair <ayman@aymanrb.com>
 * @version 0.1 beta
 * @access public
 * @see https://github.com/barbushin/php-imap 
 * @uses https://github.com/barbushin/php-imap 
 * 
 */

class MailFilters {
    
    /** 
     * The previously created instance of the ImapMailbox class (Used in deleting and moving actions of the filters)
     * @var ImapMailbox
     * @access private
     * @see __contruct(),doAction()
     */
    private $imapMailboxObj = NULL;
    
    /** 
     * A single var to hold all the user added filters
     * @var array
     * @access private
     * @see addFilters(),clearFilters(),applyFilters()
     */
    private $filters = array();
    
    
    /** 
     * To check whether any of the provided filters matched the current message it's applied to
     * @var boolean
     * @access private
     * @default false
     * @see applyFilters()
     */
    private $filterMatch = false;
    
    /** 
     * Holds all the returned user specific variables from the matched filters for one single final return
     * @var array 
     * @access private
     * @see applyFilters()
     */
    private $filterReturnedVars = array();
    
    /** 
     * If set to true, every matched action will be printed on the screen the inform you what the class is intending to do
     * @var boolean 
     * @access private
     * @see doAction()
     */
    private $debugAction = false;
    
    /*
     *===========================================================
     * End of Variables / Properties declaration                *
     *===========================================================
     */
    
    
    /**
     * Constructor method of the class
     *
     * @param ImapMailbox $imapMailboxObj
     * @return void
     * 
     */
    public function __construct(ImapMailbox $imapMailboxObj) {
        $this->imapMailboxObj = $imapMailboxObj;
    }
    
    /**
     * Adds a new single filter entry to the class for applying later on
     *
     * @param string $type (to OR from OR subject are the only 3 currently supported types of filters)
     * @param string|mixed $value (the matching value the class will be looking, wildcards(*) are accepeted)
     * @param mixed $action (the action the class should go through if the this specific filters matches with the current message | Valid keys [do,params])
     * @access public
     * @return void
     * @see applyFilters(), doAction()
     * 
     */
    public function addFilter($type, $value, $action) {
        
        $this->validateSubmittedFilter($type, $value, $action);
        
        $this->filters[strtolower($type)][] = array(
            "value" => $value,
            "action" => $action
        );
    }
    
    
    private function validateSubmittedFilter($type, $value, $action) {
        //Make sure the submitted type is Supported	
        $acceptedTypes = array(
            "to",
            "subject",
            "from"
        );
        if (!is_string($type) || !in_array($type, $acceptedTypes)) {
            throw new MailFiltersException('Cannot add filter. Invalid "type" provided: ' . $type);
        }
        
        //Make sure the type has the right value submitted with it
        switch ($type) {
            case "to":
                if (!is_array($value)) {
                    throw new MailFiltersException('Invalid "value" provided for : ' . $type . '. Expected an Array instead');
                }
                break;
            case "subject":
                if (!is_string($value)) {
                    throw new MailFiltersException('Invalid "value" provided for : ' . $type . '. Expected string instead');
                }
                break;
            case "from":
                if (!is_array($value) && !is_string($type)) {
                    throw new MailFiltersException('Invalid "value" provided for : ' . $type . '. Expected an Array or String instead');
                }
        }
    }
    
    /**
     * Clears all previously added filters (Incase you want to continue working on a message with seperate filters or something)
     *
     * @access public
     * @return void
     * 
     */
    public function clearFilters() {
        $this->filters = array();
    }
    
    
    /**
     * The actual execution of the added filters on a specific message
     *
     * @param IncomingMail $mailObj
     * @access public
     * @return boolean|mixed
     * 
     */
    
    public function applyFilters(IncomingMail $mailObj) {
        
        //Clear previous message filter execution	
        $this->filterReturnedVars = array();
        $this->filterMatch        = false;
        
        if (isset($this->filters['to'])) {
            //Include the Cc & Bcc mails too (You never know how did this mail arrive to your mailbox :] !)
            if (!empty($mailObj->cc) || !empty($mailObj->bcc)) {
                $mailObj->to = array_merge($mailObj->to, $mailObj->cc, $mailObj->bcc);
            }
            $this->applyFiltersOnTo($mailObj);
        }
        
        //From Filters
        if (isset($this->filters['from'])) {
            $this->applyFiltersOnFrom($mailObj);
        }
        
        //Subject Filters
        if (isset($this->filters['subject'])) {
            $this->applyFiltersOnSubject($mailObj);
        }
        
        //Fix the returned data
        $vars = array();
        foreach ($this->filterReturnedVars as $data) {
            if (isset($data['UserVars']) && !empty($data['UserVars'])) {
                foreach ($data['UserVars'] as $key => $var) {
                    $vars[$key] = $var;
                }
            }
        }
        if (!$this->filterMatch) { //Non of you filters matched in this message
            return false;
        }
        return $vars;
    }
    
    /**
     * This is a private method that applies only the "To" filters if added
     *
     * @param IncomingMail $mailObj - the message currently in subject of the filters check
     * @access private
     * @return void
     * 
     */
    private function applyFiltersOnTo($mailObj) {
        foreach ($this->filters['to'] as $toFilter) {
            //Just incase there was only one filter value provided (Convert to array)
            if (!is_array($toFilter['value'])) {
                $toFilter['value'] = array(
                    $toFilter['value']
                );
            }
            foreach ($toFilter['value'] as $filter_value) {
                
                $toPattern = str_replace('*', '(.*)', $filter_value);
                
                foreach ($mailObj->to as $toMail => $toName) {
                    if (preg_match('|' . $toPattern . '|', $toMail) != false) {
                        $this->filterReturnedVars[] = $this->doAction($toFilter["action"], $mailObj);
                        $this->filterMatch          = true;
                    }
                }
            }
        }
        
    }
    
    
    /**
     * This is a private method that applies only the "From" filters if added
     *
     * @param IncomingMail $mailObj - the message currently in subject of the filters check
     * @access private
     * @return void
     * 
     */
    private function applyFiltersOnFrom($mailObj) {
        foreach ($this->filters['from'] as $from_filter) {
            if (!is_array($from_filter['value'])) { //Just incase one filter is provided
                $from_filter['value'] = array(
                    $from_filter['value']
                );
            }
            
            //Just incase the Partial Match is needed we will use regular expressions
            foreach ($from_filter['value'] as $filter_value) {
                $from_pattern = str_replace('*', '(.*)', $filter_value);
                
                if (preg_match('|' . $from_pattern . '|', $mailObj->fromAddress) != false) {
                    $this->filterReturnedVars[] = $this->doAction($from_filter["action"], $mailObj);
                    $this->filterMatch          = true;
                }
            }
        }
    }
    
    
    /**
     * This is a private method that applies only the "Subject" filters if added
     *
     * @param IncomingMail $mailObj - the message currently in subject of the filters check
     * @access private
     * @return void
     * 
     */
    private function applyFiltersOnSubject($mailObj) {
        foreach ($this->filters['subject'] as $subject_filter) {
            $subject_pattern = str_replace('*', '(.*)', $subject_filter['value']);
            
            if (preg_match('|' . $subject_pattern . '|', $mailObj->subject) != false) {
                $this->filterReturnedVars[] = $this->doAction($subject_filter["action"], $mailObj);
                $this->filterMatch          = true;
            }
        }
    }
    
    /**
     * This is a private method that applies only the "Subject" filters if added
     *
     * @param mixed $action - the user specified array of actions (added in the addFilter call) 
     * @param IncomingMail $mailObj - the message currently in subject of the filters check
     * @access public
     * @return mixed
     * 
     */
    public function doAction($action, $mailObj) {
        switch ($action['do']) {
            case 'delete':
                if ($this->debugAction) {
                    echo "I am Deleting this";
                }
                return $this->imapMailboxObj->deleteMail($mailObj->id);
                break;
            case 'move':
                if ($this->debugAction) {
                    echo "I am Moving this to " . $action['params'] . "<br>";
                }
                return $this->imapMailboxObj->moveMail($mailObj->id, $action['params']);
                break;
            case 'forward':
                if ($this->debugAction) {
                    echo "I need to forward this to " . $action['params'] . "<br>";
                }
                return $this->bounceMessage($mailObj, $action['params']);
                break;
            case 'return':
                if ($this->debugAction) {
                    echo "I need to return Specific Vars here";
                }
                return array(
                    "UserVars" => $action['params']
                );
                break;
            default:
                return NULL;
        }
    }
    
    
    
    /**
     * This will forward the message to the specified mail as if it was originally sent to that mail right from the beginning (Bounce Message) 
     *
     * @access private
     * @return void
     * 
     */
    public function enableDebug() {
        $this->debugAction = true;
    }
    
    /**
     * This will forward the message to the specified mail as if it was originally sent to that mail right from the beginning (Bounce Message) 
     *
     * @param IncomingMail $mailObj - the message currently in subject of the filters check
     * @param string $mail - the mail the message will be forwarded to
     * @access private
     * @return void
     * 
     */
    private function bounceMessage($mailObj, $mail) {
        //This is still Tricky ! Need to find a way to handle this   
    }
}

class MailFiltersException extends Exception {
    
}