/*
    HUD call references.
    
    func=
        personal
        currency
        storage
        trade
    
    input=
        (personal)  - updateInventory (pulls entire inventory + item names from server) 
                    - addItem&data=id:amount (add num or id to inv)
                    - removeItem&data=id:amount (opposite of above)
                    - tradeItem&data=id:amount&targetChar=tarId (trade item)
                    - getGatherCooldown (get the current gathering cooldown)
        
        (currency)  - getInvMoney (get inventory money)
                    - tradeMoney&targetChar=tarId&amount=amount
*/
// Scripts to reset.
list whichScripts = ["Inventory HUD Personal", "Inventory HUD Trader"];
integer charid; // Character ID!
integer rdyUpd = FALSE;
string charname;
integer squawker; // Channel RP tool communicates to HUD on.
integer nodes;
integer dev = FALSE; // True if developing.
integer instantiated = FALSE;
integer reset;
integer resetting = FALSE;
integer timeout = 120;
integer rezzedGame;
integer gameCooldown;
//////////////////////////////////////////
// LINKED MESSAGE INTEGERS
/////////////////////////////////////////

integer persNum = 90800;
integer tradeNum = 90900;

///////////////////////////////////////
//string serverURL = "https://neckbeardsanon.xen.prgmr.com/rptool/inventory_test/inventory/inventory_handler.php?";
string serverURL = "https://neckbeardsanon.xen.prgmr.com/rptool/inventory/inventory_handler.php?";
key onlineCommsKey;
integer Key2AppChan(key ID, integer App) { // Generate chat channel.
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}

sendDataToServer(string data)
{
    //onlineCommsKey = llHTTPRequest(serverURL, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], "charId="+ (string)charid + "&" + data);
    onlineCommsKey = llHTTPRequest(serverURL, [HTTP_METHOD, "POST", HTTP_MIMETYPE, "application/x-www-form-urlencoded", HTTP_BODY_MAXLENGTH, 16384, HTTP_VERIFY_CERT, FALSE], data);
}

resetScripts()
{
    resetting = TRUE;
    integer i = (llGetListLength(whichScripts) - 1);
    do
    {
        llResetOtherScript(llList2String(whichScripts, i));
        --i;
    } while (i>-1);
}
integer squawk;
list paginate( integer vIdxPag, list gLstMnu ){
    list vLstRtn;
    if ((gLstMnu != []) > 12){ //-- we have more than one possible page
        integer vIntTtl = -~((~([] != gLstMnu)) / 10);                                 //-- Total possible pages
        integer vIdxBgn = (vIdxPag = (vIntTtl + vIdxPag) % vIntTtl) * 10;              //-- first menu index
        string  vStrPag = llGetSubString( "                     ", 21 - vIdxPag, 21 ); //-- encode page number as spaces
         //-- get ten (or less for the last page) entries from the list and insert back/fwd buttons
        vLstRtn = llListInsertList( llList2List( gLstMnu, vIdxBgn, vIdxBgn + 9 ), (list)(" «" + vStrPag), 0xFFFFFFFF ) +
                  (list)(" »" + vStrPag);
    }else{ //-- we only have 1 page
        vLstRtn = gLstMnu; //-- just use the list as is
    }
    return //-- fix the order for [L2R,T2B] and send it out
      llList2List( vLstRtn, -3, -1 ) + llList2List( vLstRtn, -6, -4 ) +
      llList2List( vLstRtn, -9, -7 ) + llList2List( vLstRtn, -12, -10 );
}
list menu = ["Trade",
"Destroy",
"Check Gather",
"Close"
];
list minigames = [
    "Berserker",
    "Close"
];
integer menuChan;
integer menuHandler;
string menuText = "Inventory: Select your option.";
default
{
    state_entry()
    {
        llSleep(5);
        llOwnerSay("Instantiating inventory HUD...");
        squawker = Key2AppChan(llGetOwner(), 1338);
        //persNum = Key2AppChan(llGetOwner(), 9001);
        //tradeNum = Key2AppChan(llGetOwner(), 9002);
        nodes = Key2AppChan("5675c8a0-430b-42xx-af36-60734935fad3", 744);
        squawk = llListen(squawker, "", "", "");
        llListen(nodes, "", "", "");
        llSleep(5); // Sleep for 5 seconds.
        sendDataToServer("func=personal&input=loginUpdateInventory");
    }
    on_rez(integer start)
    {
        if(!llGetAttached())
        {
            llListenRemove(squawk);
        }
    }
    touch_end(integer touched)
    {
        if(llDetectedKey(0) != llGetOwner())
        {
            return;
        }
        menuChan = Key2AppChan(llGetOwner(), 1984); // Set the menu channel.
        llListen(menuChan, "", llGetOwner(), "");
        llDialog(llGetOwner(), menuText, paginate(0, menu), menuChan);
        llSetTimerEvent(timeout);
    }
    
     timer()
    {    
        llSetTimerEvent(0);
        if(menuChan)
        {
            llListenRemove(menuHandler); // Kill the menu listener.
            menuChan = FALSE; // Unassign menuChan until we need it again.
            //llOwnerSay("Inventory menu timed out.");
        }
        if(llGetUnixTime() > gameCooldown && rezzedGame == 2)
        {
            gameCooldown = 0;
            rezzedGame = 0;
        }
    }
    
    http_response(key id, integer status, list meta, string body)
    {
        //llOwnerSay("Status: " + (string)status);
        //llOwnerSay(body);
        if(id != onlineCommsKey)
        {
            return;
        }
        if(~llSubStringIndex(body, "err:"))
        {
            // If there's an error, output and return.
            llOwnerSay(llStringTrim(llDeleteSubString(body, 0, 3), STRING_TRIM));
            return;
        }
        else if(body == "deletion:success")
        {
            charid = 0;
            llOwnerSay("Character deletion detected. Resetting.");
            resetScripts();
        }
        else if(body == "deletion:failed")
        {
            llOwnerSay("Failed to delete inventory.");
            llOwnerSay(body);
        }
        else if(~llSubStringIndex(body, "money:"))
        {
            llMessageLinked(LINK_SET, persNum, body, "");
        }
        else if(~llSubStringIndex(body, "destroyed:") && instantiated)
        {
            body = llStringTrim(llDeleteSubString(body, 0, 9), STRING_TRIM);
            llMessageLinked(LINK_SET, persNum, body, "");
        }
        else if(~llSubStringIndex(body, "fullinv:"))
        {
            if(~llSubStringIndex(body, "[[§]]"))
            {
                string bodyTmp = llGetSubString(body, (llSubStringIndex(body, "[[§]]") + 5), -1);
                body = llDeleteSubString(body, llSubStringIndex(body, "[[§]]"), -1);   
                list tmp = llParseString2List(bodyTmp, ["[[@&@]]"], []);
                charid = (integer)llList2String(tmp, 1);           
                llMessageLinked(LINK_THIS, tradeNum, "charid:" + (string)charid, "");
                charname = llList2String(tmp, 0);
                llMessageLinked(LINK_THIS, tradeNum, "charname:" + charname, "");
                if(!instantiated)
                {
                    //llOwnerSay("Character retrieved. Inventory found/created. Inventory is now active.");
                    instantiated = TRUE;
                }
                llOwnerSay("Started!");
            }
            llMessageLinked(LINK_SET, persNum, body, "");
        }
        else if(~llSubStringIndex(body, "cooldown:"))
        {
            llOwnerSay("You have " + llList2String(llParseString2List(body, [":"], []), 1) + "hr(s) until your gathering attempts reset.");
        }
        else if(~llSubStringIndex(body, "gathers:"))
        {
            llOwnerSay("You have " + llList2String(llParseString2List(body, [":"], []), 1) + " remaining gathering attempts.");
        }
        if(dev)
        {
            llOwnerSay(body);
        }
    }
    
    listen(integer c, string n, key id, string m)
    {
        if((c == squawker && llGetOwnerKey(id) == llGetOwner() && (~llListFindList(llGetAttachedList(llGetOwner()), [(string)id]))))
        {
            if(instantiated && m == "::doUpdate::")
            {
                sendDataToServer("func=personal&input=updateInventory");
            }
            else if(m == "deleted")
            {
                sendDataToServer("func=personal&input=delete");
            }
        }
        else if(c == squawker)
        {
            if(m == ("::readyUpdate::" + (string)llGetOwner()) && !rdyUpd)
            {
                rdyUpd = TRUE;
            }
            else if(m == ("::doUpdate::" + (string)llGetOwner()) && rdyUpd)
            {
                rdyUpd = FALSE;
                sendDataToServer("func=personal&input=updateInventory");
            }
        }
        else if(c == menuChan)
        {
            if(!llSubStringIndex(m, " "))
            {
                llDialog(llGetOwner(), menuText, paginate((llStringLength(m) + llSubStringIndex(m, "»") - 2), menu), menuChan);
            }
            else
            {
                if(rezzedGame == 1)
                {
                    list deets = llGetObjectDetails(llGetOwner(), [OBJECT_ROT]);
                    rotation myRot = (rotation)llList2String(deets, 0);
                    vector myPos = llGetPos();
                    vector inFront = myPos + <1.2,0,-0.3>*myRot;
                    llRegionSay(-59823, "rezgame:"+m+":" + (string)inFront + ":" + (string)myRot);
                    rezzedGame = 2;
                    gameCooldown = (llGetUnixTime() + (5*60));
                    llSetTimerEvent(timeout);
                }
                else if(m == "Trade")
                {
                    llMessageLinked(LINK_THIS, tradeNum, "::starttransaction::", NULL_KEY);
                }
                else if(m == "Destroy")
                {
                    llMessageLinked(LINK_THIS, tradeNum, "::startdeletion::", NULL_KEY);
                }
                else if(m == "Minigames")
                {
                    if(rezzedGame == 2 && llGetUnixTime() < gameCooldown)
                    {
                        llOwnerSay("You have recently rezzed a game & cannot rez one again for 5 minutes after rezzing.");
                        return;
                    }
                    else if(llGetUnixTime() > gameCooldown)
                    {
                        rezzedGame = FALSE;
                        gameCooldown = FALSE;
                    }
                    rezzedGame = 1;
                    llDialog(llGetOwner(), "Which game would you like to play?", minigames, menuChan);
                }
                else if(m == "Check Gather")
                {
                    sendDataToServer("func=personal&input=getGatherCooldown");
                }
            }
        }
    }
    
    attach(key id)
    {
        if(llGetAttached())
        {
            // If we're attached, then we've had our attach request accepted.
            //llResetScript(); // So reset!
            llOwnerSay("Stand by; starting up auxiliary scripts.");
            resetScripts();
        }
    }
    
    link_message(integer link, integer num, string body, key id)
    {
        if(num == 1)
        {
            if(!resetting)
            {
                return;
            }
            ++reset;
            if(reset >= llGetListLength(whichScripts))
            {
                llOwnerSay("Auxiliary scripts have started. Booting handler.");
                llResetScript();
            }
            else
            {
                llOwnerSay(body + " has started.");
            }
        }
        if(num == tradeNum)
        {
            if(body == "::updateinventory::")
            {
                sendDataToServer("func=personal&input=updateInventory");
            }
        }
        else if(num == (tradeNum - 2))
        {
            // This means we've deleted something.
            sendDataToServer("func=personal&input=removeItem&data=" + body);
        }
    }
}
