<?php

class psdfJasperReport {

    var $xml = null;
    var $xp = null;
    var $file = false;
    var $ns_xpdl2 = "http://www.wfmc.org/2008/XPDL2.1";

    const EXTENDED_PACKAGE = '1';
    const EXTENDED_PROCESS = '2';
    const EXTENDED_ACTIVITY = '3';

    public function  __construct($xml=false) {
        if( $xpdl ) {
            return $this->load($xpdl);
        }
    }

    public function load( $xml ) {
        $this->xml = new DOMDocument();
        // Determino si levanto de un archivo o un string
        if( file_exists($xml) ) {
            $ret = $this->xml->load($xpdl);
            $this->file = $xpdl; // Puede servirme mantenerlo
        }
        else {
            $ret = $this->xml->loadXML($xpdl);
            $this->file = false;
        }
        if( !$ret ) {
            $this->file = false;
            return false;
        }
        // Dejo ya instanciado el objeto para tratamiento xpath
        $this->xp = new domxpath( $this->xml );

        return true;
    }

    public function getPackageId() {
        return $this->xml
                ->getElementsByTagNameNS($this->ns_xpdl2, 'Package')
                ->item(0)->getAttribute('Id');
    }

    public function getPackageName() {
        return $this->xml
                ->getElementsByTagNameNS($this->ns_xpdl2, 'Package')
                ->item(0)->getAttribute('Name');
    }

    public function getPsdfPaquete() {
        return $this->getExtendedAttributeValue('PSDFPaquete');
    }

    public function getPsdfMacro() {
        return $this->getExtendedAttributeValue('PSDFMacro');
    }

    public function getPsdfMacroName() {
        return $this->getExtendedAttributeValue('PSDFMacroName');
    }

    public function getPsdfProceso($process_id) {
        return $this->getExtendedAttributeValue('PSDFProceso', self::EXTENDED_PROCESS, array($process_id,));
    }

    public function setPsdfPaquete( $paquete_id ) {
        return $this->setExtendedAttributeValue('PSDFPaquete', $paquete_id);
    }

    public function setPsdfMacro( $macro_id ) {
        return $this->setExtendedAttributeValue('PSDFMacro', $macro_id);
    }

    public function setPsdfMacroName( $name ) {
        return $this->setExtendedAttributeValue('PSDFMacroName', $name);
    }

    public function setPsdfProceso( $process_id, $proceso_id ) {
        return $this->setExtendedAttributeValue('PSDFProceso', $proceso_id, self::EXTENDED_PROCESS, array($process_id));
    }

    /**
     * Intenta determinar como nombre del macro el nombre de la carpeta que
     * contiene el archivo xpdl
     * /ruta_workspace/miproyecto/paquetes/mimacro/mipaquete.xpdl => mimacro
     * @return string Nombre del Macro o null
     */
    public function determineMacroName() {
        if( $this->file ) {
            $partes = explode(DIRECTORY_SEPARATOR, $this->file);
            if( count($partes)>0 ) {
                return $partes[count($partes)-2];
            }
        }
        return null;
    }

    /**
     * Obtengo el valor de un atributo extendido de un paquete, proceso o actividad
     *
     * @param string $name Nombre del atributo
     * @param constante $type Nivel donde setear (paquete, proceso o actividad)
     * @param array $parent Id de proceso y actividad si tengo que recuperar el atributo
     *                       de uno de ellos.
     * @return string Valor del atributo, nulo si no existe.
     */
    public function getExtendedAttributeValue($name, $type=self::EXTENDED_PACKAGE, $parent=array()) {
        $query = "/xpdl2:Package";

        if( $type==self::EXTENDED_PROCESS or $type==self::EXTENDED_ACTIVITY ) {
            $query.= "/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[@Id=\"".$parent[0]."\"]";
        }
        if( $type==self::EXTENDED_ACTIVITY) {
            $query.= "/xpdl2:Activities/xpdl2:Activity[@Id=\"".$parent[1]."\"]";
        }

        $query.= "/xpdl2:ExtendedAttributes/xpdl2:ExtendedAttribute[@Name=\"".$name."\"]";
        $nodeList = $this->getElementsByQuery( $query );
        $node = $nodeList->item(0);
        if( $node ) {
            return $node->getAttribute('Value');
        }
        return null;
    }

    /**
     * Setea un atributo extendido a nivel paquete, proceso o actividad.
     * Si el atributo aun no existe, lo crea.
     *
     * @param string $name Nombre del atributo
     * @param value $name valor del atributo, a setear.
     * @param constante $type Nivel donde setear (paquete, proceso o actividad)
     * @param array $parent Id de proceso y actividad si tengo que recuperar el atributo
     *                       de uno de ellos.
     * @return none
     */
    public function setExtendedAttributeValue($name, $value, $type=self::EXTENDED_PACKAGE, $parent=array()) {

        $query = "/xpdl2:Package";
        if( $type==self::EXTENDED_PROCESS or $type==self::EXTENDED_ACTIVITY ) {
            $query.= "/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[@Id=\"".$parent[0]."\"]";
        }
        if( $type==self::EXTENDED_ACTIVITY) {
            $query.= "/xpdl2:Activities/xpdl2:Activity[@Id=\"".$parent[1]."\"]";
        }
        $query_es = $query."/xpdl2:ExtendedAttributes";
        $query_e  = $query."/xpdl2:ExtendedAttributes/xpdl2:ExtendedAttribute[@Name=\"".$name."\"]";

        // Busco el atributo extendido (xpdl2:Extended)
        $nodeList = $this->getElementsByQuery( $query_e );
        $ext = $nodeList->item(0);
        if( !$ext ) {

            // No lo encontré entonces lo creo
            $ext = $this->xml->createElement('xpdl2:ExtendedAttribute');
            $ext->setAttribute('Name', $name);

            // Busco la etiqueta contenedora para agregarla en ella (xpdl2/ExtendedAttributes)
            $nodeList = $this->getElementsByQuery( $query_es );
            $exts = $nodeList->item(0);
            if( !$exts ) {

                // No lo encontré entonces lo creo
                $exts = $this->xml->createElement('xpdl2:ExtendedAttributes');

                // Busco el padre para agregarlo en el (paquete, proceso o actividad)
                $nodeList = $this->getElementsByQuery( $query );
                $parent = $nodeList->item(0);
                if( !$parent ) {

                    // No lo encontre, esto no deberia ser lo normal
                    return false;
                }

                $parent->appendChild($exts);
            }

            $exts->appendChild($ext);
        }

        // Actualizo el valor
        $ext->setAttribute('Value', $value);
    }

    /**
     * Obtengo el valor de un atributo extendido de un paquete, proceso o actividad
     *
     * @param string $name Nombre del atributo
     * @param constante $type Nivel donde setear (paquete, proceso o actividad)
     * @param array $parent Id de proceso y actividad si tengo que recuperar el atributo
     *                       de uno de ellos.
     * @return string Valor del atributo, nulo si no existe.
     */
    public function getExtendedAttributeBody($name, $type=self::EXTENDED_PACKAGE, $parent=array()) {
        $query = "/xpdl2:Package";

        if( $type==self::EXTENDED_PROCESS or $type==self::EXTENDED_ACTIVITY ) {
            $query.= "/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[@Id=\"".$parent[0]."\"]";
        }
        if( $type==self::EXTENDED_ACTIVITY) {
            $query.= "/xpdl2:Activities/xpdl2:Activity[@Id=\"".$parent[1]."\"]";
        }

        $query.= "/xpdl2:ExtendedAttributes/xpdl2:ExtendedAttribute[@Name=\"".$name."\"]";
        $nodeList = $this->getElementsByQuery( $query );
        $node = $nodeList->item(0);
        if( $node ) {
            return $node->textContent;
        }
        return null;
    }

    public function setExtendedAttributeBody($name, $value) {

    }

    public function getElementsByQuery( $xpath ) {
        return $this->xp->query( $xpath );
    }

    public function getContent() {
        $content = $this->xml->saveXML();
        return $content;
    }

    /**
     * Retorno un array de id y nombre de procesos del paquete
     * Recupera todos o solamente los especificados en $filters_id
     * @param array $filters_id Lista de id (xpdl) de procesos a filtrar
     *                          Si se omite recupera todos.
     * @return array Lista de procesos obtenida
     */
    public function getProcessArray($filters_id=array()) {
        $procs = array();
        if( count($filters_id)>0 ) {
            foreach( $filters as $id) {
                $query = "/xpdl2:Package/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[@Id=\"%s\"]";
                $query = sprintf($query, $idXpdl);
                $nodeList = $this->getElementsByQuery( $query );
                foreach( $nodeList as $node ) {
                    $pr['id'] = $node->getAttribute( "Id" );
                    $pr['name'] = $node->getAttribute( "Name" );
                    $pr['psdf_id'] = $this->getPsdfProceso($pr['id']);
                    $procs[] = $pr;
                }
            }
        }
        else {
            $query = "/xpdl2:Package/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess";
            $nodeList = $this->getElementsByQuery( $query );
            foreach ( $nodeList as $node ) {
                $pr['id'] = $node->getAttribute( "Id" );
                $pr['name'] = $node->getAttribute( "Name" );
                $pr['psdf_id'] = $this->getPsdfProceso($pr['id']);
                $procs[] = $pr;
            }
        }
        return $procs;
    }

    /**
     * Actualiza el archivo con el contenido actual del xpdl. Esto si previamente
     * fue levantado desde un archivo
     */
    public function file_save() {
        if( $this->file ) {
            $data = $this->getContent();
            file_put_contents($this->file, $data);
        }
    }

    public function getTypeDeclarations() {
        $typeDeclarations = array();

        $query = "/xpdl2:Package/xpdl2:TypeDeclarations/xpdl2:TypeDeclaration";
        $nodeList = $this->getElementsByQuery( $query );
        foreach ( $nodeList as $node ) {
            $id = $node->getAttribute( "Id" );
            $name = $node->getAttribute( "Name" );

            $description = null;
            if( $node->getElementsByTagName("Description")->length > 0 ) {
                $description = $node->getElementsByTagName("Description")->item(0)->textContent;
            }

            // Tipo de dato basico
            $type = null;
            $length = null;
            $decimal = null;
            if( $node->getElementsByTagName("BasicType")->length > 0 ) {
                $type = $node->getElementsByTagName("BasicType")->item(0)->getAttribute("Type");
                if( $node->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Length")->length > 0)
                    $length = $node->getElementsByTagName("BasicType")
                            ->item(0)->getElementsByTagName("Length")->item(0)->textContent;
                if( $node->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Precision")->length > 0)
                    $length = $node->getElementsByTagName("BasicType")
                            ->item(0)->getElementsByTagName("Precision")->item(0)->textContent;
                if( $node->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Scale")->length > 0)
                    $decimal = $node->getElementsByTagName("BasicType")
                            ->item(0)->getElementsByTagName("Scale")->item(0)->textContent;
            }

            // Tipo de dato declarativo
            $declaredType = null;
            if( $node->getElementsByTagName("DeclaredType")->length > 0 ) {
                $type = 'DeclaredType';
                $declaredType = $node->getElementsByTagName("DeclaredType")->item(0)->getAttribute('Id');
            }

            // Referencia Externa
            $externalReference = null;
            if( $node->getElementsByTagName("ExternalReference")->length > 0 ) {
                $type = 'ExternalReference';
                $externalReference =
                        $node->getElementsByTagName("ExternalReference")->item(0)->getAttribute('location') . '|' .
                        $node->getElementsByTagName("ExternalReference")->item(0)->getAttribute('namespace') . '|' .
                        $node->getElementsByTagName("ExternalReference")->item(0)->getAttribute('xref');
            }

            $typeDeclarations[$id] = array(
                    'name' => $name,
                    'description' => $description,
                    'type' => $type,
                    'length' => $length,
                    'decimal' => $decimal,
                    'externalReference' => $externalReference,
                    'declaredType' => $declaredType,
            );
        }
        return $typeDeclarations;
    }

    /**
     * Recupero datafields del paquete si no se especifica xpdl_process_id
     * o del proceso si se especifica su id
     * @param string $process_id Id xpdl del proceso
     * @return array Lista de datafields
     */
    public function getDataFields( $process_id=false ) {
        $dataFields = array();

        $query = "/xpdl2:Package";
        if( $process_id ) {
            $query.= "/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[ @Id=\"".$process_id."\"]";
        }
        $query.= "/xpdl2:DataFields/xpdl2:DataField";

        $nodeList = $this->getElementsByQuery($query );
        foreach ( $nodeList as $node ) {
            $id = $node->getAttribute( "Id" );
            $name = $node->getAttribute( "Name" );
            $isArray = $node->getAttribute( "IsArray" );
            $readOnly = $node->getAttribute( "ReadOnly" );

            $description = null;
            if( $node->getElementsByTagName("Description")->length > 0 )
                $description = $node->getElementsByTagName("Description")->item(0)->textContent;

            $initialValue = null;
            if( $node->getElementsByTagName("InitialValue")->length > 0 )
                $initialValue = $node->getElementsByTagName("InitialValue")->item(0)->textContent;

            $dataType = null;
            if( $node->getElementsByTagName("DataType")->length > 0 )
                $dataType = $node->getElementsByTagName("DataType")->item(0);

            // Tipo de dato basico
            $type = null;
            $length = null;
            $decimal = null;
            if( $dataType->getElementsByTagName("BasicType")->length > 0 ) {
                $type = $dataType->getElementsByTagName("BasicType")->item(0)->getAttribute("Type");
                if( $dataType->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Length")->length > 0)
                    $length = $dataType->getElementsByTagName("BasicType")
                            ->item(0)->getElementsByTagName("Length")->item(0)->textContent;
                if( $dataType->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Precision")->length > 0)
                    $length = $dataType->getElementsByTagName("BasicType")
                            ->item(0)->getElementsByTagName("Precision")->item(0)->textContent;
                if( $dataType->getElementsByTagName("BasicType")->item(0)->getElementsByTagName("Scale")->length > 0)
                    $decimal = $dataType->getElementsByTagName("BasicType")
                            ->item(0)->getElementsByTagName("Scale")->item(0)->textContent;
            }

            // Tipo de dato declarativo
            $declaredType = null;
            if( $dataType->getElementsByTagName("DeclaredType")->length > 0 ) {
                $type = 'DeclaredType';
                $declaredType = $dataType->getElementsByTagName("DeclaredType")->item(0)->getAttribute('Id');
            }

            // Referencia Externa
            $externalReference = null;
            if( $dataType->getElementsByTagName("ExternalReference")->length > 0 ) {
                $type = 'ExternalReference';
                $externalReference =
                        $dataType->getElementsByTagName("ExternalReference")->item(0)->getAttribute('location') . '|' .
                        $dataType->getElementsByTagName("ExternalReference")->item(0)->getAttribute('namespace') . '|' .
                        $dataType->getElementsByTagName("ExternalReference")->item(0)->getAttribute('xref');
            }

            $dataFields[$name] = array(
                    'id' => $id,
                    'isArray' => $isArray,
                    'readOnly' => $readOnly,
                    'description' => $description,
                    'type' => $type,
                    'length' => $length,
                    'decimal' => $decimal,
                    'externalReference' => $externalReference,
                    'declaredType' => $declaredType,
                    'initialValue' => $initialValue,
            );
        }
        return $dataFields;
    }

    /**
     * Recupero participante del paquete si no se especifica xpdl_process_id
     * o del proceso si se especifica su id
     * @param string $xpdl_process_id Id xpdl del proceso
     * @return array Lista de participantes
     */
    public function getParticipants( $process_id=false ) {
        $participants = array();

        $query = "/xpdl2:Package";
        if( $process_id ) {
            $query.= "/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[ @Id=\"".$process_id."\"]";
        }
        $query.= "/xpdl2:Participants/xpdl2:Participant";

        $nodeList = $this->getElementsByQuery( $query );
        foreach ( $nodeList as $node ) {
            $id = $node->getAttribute( "Id");
            $name = $node->getAttribute( "Name");

            $description = null;
            if( $node->getElementsByTagName("Description")->length > 0 )
                $description = $node->getElementsByTagName("Description")->item(0)->textContent;

            $type = null;
            if( $node->getElementsByTagName("ParticipantType")->length > 0 )
                $type = $node->getElementsByTagName("ParticipantType")->item(0)->getAttribute('Type');

            $participants[$name] = array(
                    'id' => $id,
                    'description' =>$description,
                    'type' => $type,
            );
        }
        return $participants;
    }

    public function getActivities( $process_id=false ) {
        $activities = array();

        $query = "/xpdl2:Package";
        $query.= "/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[ @Id=\"".$process_id."\"]";
        $query.= "/xpdl2:Activities/xpdl2:Activity";

        $nodeList = $this->getElementsByQuery($query );
        foreach ( $nodeList as $node ) {
            $id = $node->getAttribute( "Id" );
            $name = $node->getAttribute( "Name" );
            $type = $this->getActivityType($node);
            $isAutocomplete = $this->getActivityIsAutoComplete($process_id, $id);

            $activities[$name] = array(
                    'id' => $id,
                    'name' => $name,
                    'type' => $type,
                    'is_autocomplete' => $isAutocomplete,
            );
        }
        return $activities;
    }

    /**
     * Valida si el proceso ya existe en el paquete
     * Puede recibir
     *   el Id objeto Proceso (123)
     *   el Id Xpdl           (_XDFBA...)
     *   el Nombre            (ejemplo1)
     * @param $processId
     * @return boolean
     */
    public function processExist($process) {
        $filter = sprintf('@Name = "%s"', $process);

        if( is_numeric($process) )
            $filter = sprintf('@Id = "%s"', "_".$process);

        if( substr($process, 0, 1)=="_" )
            $filter = sprintf('@Id = "%s"', $process);

        $xp = new domxpath( $this->xml );
        $nodeList = $xp->query( "/xpdl2:Package/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[ ".$filter." ]" );

        if( $nodeList->length > 0 )
            return true;
        else
            return false;
    }

    /**
     * Retorna el tipo de tarea/actividad estandarizada BPMN
     * @param $pNodeActivity
     * @return string
     */
    public function getActivityType($pNodeActivity) {
        // Por defecto vacio para las no implementadas aun
        $activityType = '';

        // Activity/Event/(StartEvent, IntermediateEvent, EndEvent)
        $nodesEvent = $pNodeActivity->getElementsByTagName("Event");
        if( $nodesEvent->length > 0 ) {
            $types = array( 'StartEvent', 'IntermediateEvent', 'EndEvent' );
            foreach( $types as $type) {
                $nodes = $nodesEvent->item(0)->getElementsByTagName($type);
                if( $nodes->length > 0 ) {
                    $activityType = $type;
                    break;
                }
            }
        }

        // Activity/Implementation/Task/(TaskService, TaskReceive, TaskManual,
        // TaskReference, TaskScript, TaskSend, TaskUser, TaskApplication)
        $nodesImpl = $pNodeActivity->getElementsByTagName("Implementation");
        if( $nodesImpl->length > 0 ) {
            $nodesTask = $nodesImpl->item(0)->getElementsByTagName("Task");
            if( $nodesTask->length > 0 ) {
                $types = array( 'TaskService', 'TaskReceive', 'TaskManual', 'TaskReference',
                        'TaskScript', 'TaskSend', 'TaskUser', 'TaskApplication' );
                foreach( $types as $type) {
                    $nodes = $nodesTask->item(0)->getElementsByTagName($type);
                    if( $nodes->length > 0 ) {
                        $activityType = $type;
                        break;
                    }
                }
            }
        }

        return $activityType;
    }

    /**
     * Verifica el tipo de finalizacion de la tarea.
     * Retorna True si la tarea se autocompleta o false si nó   *
     * @param $pNodeActivity
     * @return bolean
     */
    public function getActivityIsAutoComplete( $process_id, $activity_id ) {
        $valor = $this->getExtendedAttributeValue(
                    'Autocompletar', self::EXTENDED_ACTIVITY, array($process_id, $activity_id));
        if( $valor=='1' or $valor=='true') {
            return true;
        }
        return false;
    }

    public function getActivityIsAutoStart($activityXpdlId) {
        $valor = $this->getExtendedAttributeValue(
                'Autoiniciar', self::EXTENDED_ACTIVITY, array($process_id, $activity_id));
        if( $valor=='1' or $valor=='true') {
            return true;
        }
        return false;
    }

    public function getNextActivities($process_id, $activity_id) {
        $xp2 = new domxpath( $this->xml );

        $activities = array();

        // Obtengo transiciones cuyo origen es la actividad actual
        $nodeList = $this->getElementsByQuery(
            "/xpdl2:Package/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[ @Id=\"".$process_id."\"]".
            "/xpdl2:Transitions/xpdl2:Transition[ @From=\"".$activity_id."\" ]" );
        foreach ( $nodeList as $node ) {
            // Obtengo el nodo y nombre de la actividad destino
            $nodeList2 = $xp2->query(
                "/xpdl2:Package/xpdl2:WorkflowProcesses/xpdl2:WorkflowProcess[ @Id=\"".$process_id."\"]".
                "/xpdl2:Activities/xpdl2:Activity[ @Id=\"".$node->getAttribute("To")."\" ]" );

            $act['id'] = $nodeList2->item(0)->getAttribute('Id');
            $act['name'] = $nodeList2->item(0)->getAttribute('Name');
            $act['type'] = $this->getActivityType($nodeList2->item(0));
            $activities[] = $act;
        }

        return $activities;
    }

    /**
     * Recupera lista definicion de patrones
     *
     * @param string $process_id Id xpdl del proceso
     * @param string $activity_id Id xpdl de la actividad
     * @return array Lista de patrones
     */
    public function getPsdfPatterns($process_id, $activity_id) {
        $patterns = array();

        // Hoy solo tomo el primero, si hay mas de uno será omitido
        $ymldef = $this->getExtendedAttributeBody(
                    'Patron', self::EXTENDED_ACTIVITY, array($process_id, $activity_id));

        if( !$ymldef ) {
            $ymldef="Foo: {}"; // Patron por defecto si no se especificó
        }

        try {
            $pattern = sfYaml::load($ymldef);
        }catch (Exception $e) {
            throw new sfException(sprintf('No se pudo leer yml de llamada a patron: %s', $e->getMessage()));
        }

        // Quite el raiz Patron asi el 2do (Nombre) pasa a ser el 1ro.
        if( $pattern ) {
            $patterns[key($pattern)] = $pattern[key($pattern)];
        }

        return $patterns;
    }
}

?>