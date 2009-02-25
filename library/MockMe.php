<?php

class MockMe
{

    protected $_className = null;
    protected $_mockClassName = null;

    public function __construct($className, $customName = null)
    {
        $this->_className = $className;
        $this->_createMockObject($className, $customName);
    }

    public static function mock($className, $customNameOrArray = null)
    {
        $customName = null;
        $mock = null;
        if (!is_array($customNameOrArray)) {
            $customName = $customNameOrArray;
        }
        $mocker = new self($className, $customName);
        $mockClass = $mocker->getMockClassName();
        $reflectedClass = new ReflectionClass($mockClass);

        /**
         * Constructor handling here is pretty detailed; more than strictly
         * needed. This poses the solution of handling constructor definitions
         * in abstract and interface classes which should be obeyed to create
         * a successful Mock.
         */
        if ($reflectedClass->hasMethod('__construct')) {
            $ctorMethod = $reflectedClass->getMethod('__construct');
            $ctorParams = $ctorMethod->getParameters();
            if (count($ctorParams) == 0) {
                $mock = $reflectedClass->newInstance();
            } else {
                $params = array();
                foreach ($ctorParams as $param) {
                    if ($param->isOptional()) {
                        $params[] = null;
                        continue;
                    }
                    if ($param->isArray()) {
                        $params[] = array();
                        continue;
                    }
                    $classHint = $param->getClass();
                    if ($classHint) {
                        $params[] = $classHint->newInstance();
                        continue;
                    }
                    $params[] = null; // default - just give it anything
                }
                $mock = $reflectedClass->newInstanceArgs($params);
            }
        } else {
            $mock = $reflectedClass->newInstance();
        }

        /**
         * Allow quick stubbing of classes by passing array of
         * method names and canned return values.
         */
        if (is_array($customNameOrArray)) {
            foreach($customNameOrArray as $method => $return) {
                $mock->shouldReceive($method)
                     ->zeroOrMoreTimes()
                     ->andReturn($return);
            }
        }
        return $mock;
    }

    public function getClassName()
    {
        return $this->_className;
    }

    public function getMockClassName()
    {
        return $this->_mockClassName;
    }

    protected function _createMockObject($className, $customName = null)
    {
        if (is_null($customName)) {
            $this->_mockClassName = 'MockMe_' . sha1(microtime());
        } else {
            $this->_mockClassName = $customName;
        }
        $classDefinition = $this->_generateMockClassDefinition($this->_mockClassName, $className);

        eval($classDefinition);
    }

    protected function _generateMockClassDefinition($mockClassName, $className)
    {
        if (!class_exists($className, true) && !interface_exists($className, true)) {
            throw new MockMe_Exception('class or interface ' . $className . ' does not exist');
        }
        $reflectedClass = new ReflectionClass($className);
        if ($reflectedClass->isInterface()) {
            $classDef = $this->_generateDefForInterface($mockClassName, $reflectedClass);
        } else {
            $classDef = $this->_generateDefForClass($mockClassName, $reflectedClass);
        }
        return $classDef;
    }

    protected function _generateDefForClass($mockClassName, ReflectionClass $reflectedClass)
    {
        $className = $reflectedClass->getName();
        if ($reflectedClass->isFinal()) {
            throw new MockMe_Exception('may not inherit from final class');
        }
        $classDef = "class $mockClassName extends $className {";
        $classDef .= $this->_generateDefForMethods($reflectedClass);
        $classDef .= $this->_generateDefForMockInterface();
        $classDef .= "}";
        return $classDef;
    }

    protected function _generateDefForInterface($mockClassName, ReflectionClass $reflectedClass)
    {
        $className = $reflectedClass->getName();
        $classDef = "class $mockClassName implements $className {" . PHP_EOL;
        $classDef .= $this->_generateDefForMethods($reflectedClass);
        $classDef .= $this->_generateDefForMockInterface();
        $classDef .= "}";
        return $classDef;
    }

    protected function _generateDefForMockInterface()
    {
        $classDef = '';

        $classDef .= PHP_EOL . 'protected $_expectations = array();';
        $classDef .= PHP_EOL . 'protected $_verified = false;';
        $classDef .= PHP_EOL . 'protected $_orderedNumber = null;';
        $classDef .= PHP_EOL . 'protected $_orderedNumberNext = null;';

        $classDef .= PHP_EOL . 'public function verify() {'
    	           . PHP_EOL . '   if ($this->_verified) {'
    	           . PHP_EOL . '       return $this->_verified;'
    	           . PHP_EOL . '   }'
    	           . PHP_EOL . '   $this->_verified = true;'
    	           . PHP_EOL . '   foreach ($this->_expectations as $methodName => $director) {'
    	           . PHP_EOL . '       $director->verify();'
    	           . PHP_EOL . '   }'
    	           . PHP_EOL . '   return $this->_verified;'
    	           . PHP_EOL . '}';

        $classDef .= PHP_EOL . 'public function setVerifiedStatus($bool) {'
                   . PHP_EOL . '    $this->_verified = $bool;'
                   . PHP_EOL . '}';

        $classDef .= PHP_EOL . 'public function shouldReceive($methodName) {'
                   . PHP_EOL . '    if (!isset($this->_expectations[$methodName])) {'
                   . PHP_EOL . '        $this->_expectations[$methodName] = new MockMe_Expectation_Director($methodName);'
                   . PHP_EOL . '    }'
                   . PHP_EOL . '    $expectation = new MockMe_Expectation($methodName, $this);'
                   . PHP_EOL . '    $this->_expectations[$methodName]->addExpectation($expectation);'
                   . PHP_EOL . '    return $expectation;'
                   . PHP_EOL . '}';

        $classDef .= PHP_EOL . 'public function __call($methodName, array $args) {'
                   . PHP_EOL . '    $return = null;'
                   . PHP_EOL . '    $return = $this->_expectations[$methodName]->call($args, $this);'
                   . PHP_EOL . '    return $return;'
                   . PHP_EOL . '}';

        $classDef .= PHP_EOL . 'public function getOrderedNumberNext() {'
                   . PHP_EOL . '    if (is_null($this->_orderedNumberNext)) {'
                   . PHP_EOL . '        $this->_orderedNumberNext = 1;'
                   . PHP_EOL . '        return $this->_orderedNumberNext;'
                   . PHP_EOL . '    }'
                   . PHP_EOL . '    $this->_orderedNumberNext++;'
                   . PHP_EOL . '    return $this->_orderedNumberNext;'
                   . PHP_EOL . '}';

        $classDef .= PHP_EOL . 'public function getOrderedNumber() {'
                   . PHP_EOL . '    return $this->_orderedNumber;'
                   . PHP_EOL . '}';

        $classDef .= PHP_EOL . 'public function incrementOrderedNumber() {'
                   . PHP_EOL . '    $this->_orderedNumber++;'
                   . PHP_EOL . '}';

        return $classDef;
    }

    protected function _generateDefForMethods(ReflectionClass $reflectedClass)
    {
        $classDef = '';
        $methods = $reflectedClass->getMethods();
        foreach ($methods as $method) {
            if ($method->isPublic() && !$method->isFinal() && !$method->isDestructor() && $method->getName() !== '__clone') {
                $classDef .= $this->_generateDefForMethod($method);
            }
        }
        return $classDef;
    }

    protected function _generateDefForMethod(ReflectionMethod $reflectedMethod)
    {
        $methodDef = PHP_EOL;
        $methodName = $reflectedMethod->getName();
        $methodDef .= $this->_generateDefForMethodHeader($reflectedMethod);
        if ($reflectedMethod->getName() !== '__construct') {
            $methodDef .= PHP_EOL . '   $args = func_get_args();'
                    . PHP_EOL . '   return $this->__call("' . $methodName . '", $args);'
                    . PHP_EOL . '}';
        } else {
            $methodDef .=  PHP_EOL . '}'; // empty but valid contructor with all params
                                          // necessary to support interface/abstract defs
        }
        return $methodDef;
    }

    protected function _generateDefForMethodHeader(ReflectionMethod $reflectedMethod)
    {
        $methodDef = '';
        $methodName = $reflectedMethod->getName();
        $methodParams = array();
        $params = $reflectedMethod->getParameters();
        foreach ($params as $param) {
            $paramDef = '';
            if ($param->isArray()) {
                $paramDef .= 'array ';
            } elseif ($param->getClass()) {
                $paramDef .= $param->getClass()->getName() . ' ';
            }
            $paramDef .= '$' . $param->getName();
            if ($param->isOptional()) {
                $paramDef .= ' = ';
                if ($param->isDefaultValueAvailable()) {
                    $paramDef .= var_export($param->getDefaultValue(), true);
                }
            }
            $methodParams[] = $paramDef;
        }
        $paramDef = implode(',', $methodParams);
        if ($reflectedMethod->isStatic()) {
            $methodDef .= 'public static function ' . $methodName; // @fail $this in static context
        } else {
            $methodDef .= 'public function ' . $methodName;
        }
        $methodDef .=           ' (' . $paramDef . ') {';
        return $methodDef;
    }

}