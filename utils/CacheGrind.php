<?php

class CacheGrind
{
	/**
	 * Functions calls profiles
	 */
	protected $functions = array();

	/**
	 * String name of main function
	 */
	const ENTRY_POINT = '{main}';

    /**
	 * Extract information from $inFile and store in preprocessed form in $outFile
	 *
	 * @param string $inFile Callgrind file to read
	 * @return void
	 **/
	public function parse($inFile)
	{
		$in = @fopen($inFile, 'rb');
        if (!$in) {
            throw new Exception('Could not open ' . $inFile . ' for reading.');
        }

		$currentFile = '';
        // Read information into memory
        while (($line = fgets($in))) {
            if (substr($line, 0, 3) === 'fl=') {
                $currentFile = substr(trim($line), 3); // Capture file name
            }

            if (substr($line, 0, 3) === 'fn=') {
                // Read function name
                $function = substr(trim($line), 3);

                // Handle functions with numeric names (e.g., fn=(33))
                if (preg_match('/^\((\d+)\)$/', $function, $matches)) {
                    $function = "Function #{$matches[1]}";
                }

                if (!isset($this->functions[$function])) {
                    $this->functions[$function] = array(
                        'filename' => $currentFile ?? 'unknown',
                        'invocationCount' => 0,
                        'summedSelfCost' => 0,
                        'summedInclusiveCost' => 0,
						'count' => 0,
                    );
                }
                $this->functions[$function]['invocationCount']++;
            }

            if (substr($line, 0, 4) === 'cfn=') {
                // Capture called function namecd
                $calledFunction = substr(trim($line), 4);

                // Handle called functions with numeric names (e.g., cfn=(2329))
                if (preg_match('/^\((\d+)\)$/', $calledFunction, $matches)) {
                    $calledFunction = "Function #{$matches[1]}";
                }

                if (!isset($this->functions[$calledFunction])) {
                    $this->functions[$calledFunction] = array(
                        'filename' => $currentFile ?? 'unknown',
                        'invocationCount' => 0,
                        'summedSelfCost' => 0,
                        'summedInclusiveCost' => 0,
                    );
                }
                fgets($in); // Skip line with call location
                if (preg_match('/^\s*(\d+)\s+(\d+)/', fgets($in), $matches)) {
                    $cost = (int) $matches[2];
                    $this->functions[$calledFunction]['summedInclusiveCost'] += $cost;
                }
            }

            // Capture cost lines
            if (isset($function) && preg_match('/^\s*(\d+)\s+(\d+)/', $line, $matches)) {
                $cost = (int) $matches[2];
                $this->functions[$function]['summedSelfCost'] += $cost;
                $this->functions[$function]['summedInclusiveCost'] += $cost;
            }
        }
    }

    public function getFunctions()
    {
        return $this->functions;
    }

    public function summarize()
    {
        // Order by function self cost
        uasort($this->functions, array($this, 'compareFunctions'));

        $totalTime = array_sum(array_column($this->functions, 'summedSelfCost'));

        foreach ($this->functions as $function => $statistic) {
            $this->functions[$function]['avgSelfCost'] = $statistic['invocationCount'] > 0
            ? $statistic['summedSelfCost'] / $statistic['invocationCount']
            : 0;

            $this->functions[$function]['avgInclusiveCost'] = $statistic['invocationCount'] > 0
            ? $statistic['summedInclusiveCost'] / $statistic['invocationCount']
            : 0;

            $this->functions[$function]['selfCostPercentage'] = $totalTime > 0
            ? $statistic['summedSelfCost'] / $totalTime * 100
            : 0;

            $this->functions[$function]['summedInclusiveCostPercentage'] = $totalTime > 0
            ? $statistic['summedInclusiveCost'] / $totalTime * 100
            : 0;
        }
    }

    protected function compareFunctions($a, $b)
    {
        return $b['summedSelfCost'] <=> $a['summedSelfCost'];
    }
}
