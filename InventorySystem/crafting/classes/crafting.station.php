<?php
    require_once('../classes/database.php');

    class station
    {
        private $invPdo;
        private $srvPdo;
        private $uuid;
        private $charDetails;
        private $charClassDetails;
        private $stationClass;
        function __construct($uuid, $class) // Construct the object and get all relevant player information.
        {
            $this->uuid = $uuid;
            $this->stationClass = $class;
            $this->invPdo = connectToInventory();
            $this->srvPdo = connectToRptool();
            $this->getAllCharDetails();
        }
        function chkChar()
        {
            if(!$this->charClassDetails)
            {
                return false;
            }
            else
            {
                return true;
            }
        }
        function addCrafter()
        {
            if(!$this->charClassDetails)
            {
                // Code to add crafting job!
                $stmt = " 
                    INSERT INTO crafters
                    (char_id,job) VALUES (?,?)
                ";
                $do = $this->invPdo->prepare($stmt);
                if(!$do->execute([$this->charDetails['character_id'], $this->stationClass]))
                {
                    exit("err:Could not add crafter job.");
                }
                return "success:addjob:" . $this->stationClass;
            }
            else
            {
                if($this->charClassDetails['job'] !== $this->stationClass)
                {
                    return "fail:addjob:diffjob";
                }
                else
                {
                    return "fail:addjob:samejob";
                }
            }
        }
        function removeCrafter()
        {
            // Function to remove a crafting job.
            if(!$this->charClassDetails)
            {
                return "fail:rmjob:nojob";
            }
            $stmt = "DELETE FROM crafters WHERE char_id = ?";
            $do = $this->invPdo->prepare($stmt);
            if(!$do->execute([$this->charDetails['character_id']]))
            {
                exit("err:Could not remove crafter job.");
            }
            return "success:rmjob:" . $this->stationClass;
        }
        function jobChk()
        {
            if($this->charClassDetails['job'] !== $this->stationClass)
            {
                return false;
            }
            else
            {
                return true;
            }
        }
        function characterName()
        {
            return explode("=>", $this->charDetails['titles'])[0];
        }
        function getCharClassDetails()
        {
            return $this->charClassDetails;
        }
        function getCharDetails()
        {
            return $this->charDetails;
        }
        function getAllCharDetails()
        {
            // Get character ID.
            $stmt = "SELECT lastchar FROM users WHERE uuid = ?";
            $do = $this->srvPdo->prepare($stmt);
            try
            {
                $do->execute([$this->uuid]);
            }
            catch (Exception $e)
            {
                die("err:" . $e->getMessage());
            }
            $do = $do->fetch(PDO::FETCH_ASSOC);
            // Get character details for character ID and input into charDetails variable.
            $stmt = "SELECT * FROM rp_tool_character_repository WHERE character_id = ?";
            $this->charDetails = $do['lastchar'];
            $do = $this->srvPdo->prepare($stmt);
            try
            {
                $do->execute([$this->charDetails]);
            }
            catch (Exception $e)
            {
                die("err:" . $e->getMessage());
            }
            $this->charDetails = $do->fetch(PDO::FETCH_ASSOC);
            if(!$this->charDetails or $this->charDetails['character_id'] === "0")
            {
                die("err:Invalid character ID. Character does not exist. Failed ID: " . $this->charDetails);
            }
            // Then get class details.
            $stmt = "SELECT * FROM crafters WHERE char_id = ?";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                $do->execute([$this->charDetails['character_id']]);
                $do = $do->fetch(PDO::FETCH_ASSOC);
            }
            catch (Exception $e)
            {
                die("err:" . $e->getMessage());
            }
            if(!$do)
            {
                // If no rows were affected, set class details to false.
                $this->charClassDetails = false;
            }
            else
            {
                // Otherwise put the array in it.
                $this->charClassDetails = $do;
            }
        }
        function chkStats()
        {
            // Return a list of stats.
            $stmt = "SELECT experience,exp_to_level,level FROM crafters WHERE char_id = ?";
            $do = $this->invPdo->prepare($stmt);
            try
            {
                $do->execute([$this->charDetails['character_id']]);
                $do = $do->fetch(PDO::FETCH_ASSOC);
            }
            catch (Exception $e)
            {
                exit("err:".$e->getMessage());
            }
            if(!$do)
            {
                return "chkstats:nojob";
            }
            return "chkstats:".$do['experience'].":".$do['exp_to_level'].":".$do['level'];
        }
    }
?>