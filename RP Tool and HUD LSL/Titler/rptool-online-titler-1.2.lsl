/*
    RP Tool Titler Module
    // Last updated Feb 2nd, 22
*/
float version = 1.2;

/*
    Functions: Handle all titler processing + data.
*/

/*
    Updated: March 22nd, 2019
    Change: Added speakOut function to confirm updates to titler when AFK or OOC.

    Update (March 25th, 2019)
    Change: Made the RP tool use the user-defined coolour when AFK or OOC.
    
    Update (March 31st, 2019)
    Change: Fixed a bug that caused a layout error in the AFK. (321-324)
    Change: Fixed issue with textbox updates not catching newlines on the name and erasing them. (line 881-894)
    
    Update (April 3rd, 2019)
    Change: Added talk-back for when you're OOC/AFK and you change your comma profile.
    Change: Added talk-back to confirm post regen.
    Change: Added more colour presets.
    
    Updated: October 22nd, 2021
    Added support for inventory system.

    Updated: June 4th, 2022
    Added support for faction ranks
*/

// VARS \\
integer settingsChan = 1; // Default settings channel.
integer settingsHandler;
integer dialogHandler;
integer dialogChan;
integer changing; // 1 = title, 2 = const
integer line;
integer squawker;
string factionRank = "null";
list constant = [
    "Name:", // const1
    "Species:", // const2
    "Mood:", // const3
    "Info:", // const4
    "Body:", // const5
    "Scent:", // const6
    "Currently:", // const7
    "Energy:" // const8
        ];

list title = [
    "Martin", // Name (title1)
    "n/a", // title2
    "n/a", // title3
    "n/a", // title4
    "n/a", // title5
    "n/a", // title6
    "n/a", // title7
    "n/a" // title8
        ];

list options = [
    "255,255,255", // 0
    1.0, // 1
    0, // Comma // 0 = newline separation, 1 = comma separation, 2 = whitespace separation //// 2
    0, // Postregen //// 3
    100, // Postregen max //// 4
    0/*, // Percentage; 2 = x / max, 1 = on, 0 = off. //// 5
    1 // Autosave; 1 = on, 0 = off //// 6 */
];

integer charid = 0;

list colors = [
    "blue", "0,0,255",
    "red", "255,0,0",
    "green", "0,255,0",
    "white", "255,255,255",
    "black", "0,0,0",
    "yellow", "255,255,0",
    "purple", "128,0,128",
    "aqua", "0,255,255",
    "teal", "128,255,255",
    "moccasin", "225,228,181",
    "peachpuff", "238,203,173",
    "khaki", "240,230,140",
    "firebrick", "178,34,34",
    "tomato", "255,99,71",
    "maroon", "128,0,0",
    "gray", "128,128,128",
    "lightsalmon", "255,160,122",
    "salmon", "250,128,114",
    "goldenrod", "218,165,32",
    "darkgreen", "0,100,0",
    "limegreen", "50,205,50",
    "springgreen", "0,255,127",
    "turquoise", "64,224,208",
    "steelblue", "70,130,180",
    "hotpink", "255,105,180",
    "orchid", "218,112,214"
        ];

list filter = [ // Filter out tags used to manage character info on the server.
    "=>",
    ">>",
    "@T@",
    "=" // Replace with #
        ];



integer status = 0; // 0 = IC, 1 = OOC, 2 = AFK.
string ooc = "@invis@"; // OOC text.
string afk = "@invis@"; // AFK text.
integer postregenInterval = 120;
integer lastPostregen;
integer hud_channel;

// FUNCTIONS \\

integer Key2AppChan(key ID, integer App) {
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}

string compileTitlerData()
{
    // Compile constants and titles.
    
    string out = llDumpList2String(constant, "=>") + "@T@" + llDumpList2String(title, "=>") + "@T@" + llDumpList2String(options, "=>");
    if(~llSubStringIndex(out, "%"))
    { // Because %s will mess up the database entry.
        do {
        integer inx = llSubStringIndex(out, "%");
        //integer enx = (inx + 1);
        out = llDeleteSubString(out, inx, inx);
        out = llInsertString(out, inx, "{perc}"); // Replace % with {perc}. Titler will transform {perc} to % again when loading the character.
        }
        while(~llSubStringIndex(out, "%"));
    }
    out = llEscapeURL(out);
    //llOwnerSay(out);
    return "&data="+out+"&charid="+(string)charid;
}

integer isInteger(string data)
{
    if ( (string) ( (integer) data) == data)
    {
        return TRUE;
    }
    return FALSE;
}

setText() {
    string out;
    string colour = "<"+llList2String(options, 0)+">";
    float alpha = (float)llList2String(options, 1);
    integer x;
    integer y = 7;
    if(!status)
    {
        integer comma = llList2Integer(options, 2); // 0 = newline separation, 1 = comma separation, 2 = whitespace separation
        do
        {
            string constTmp = llList2String(constant, x);
            string titleTmp = llList2String(title, x);
            if(x == 0)
            {
                out += constTmp + " " + titleTmp;
                if(comma == 2)
                {
                    out += " ";
                }
                else if(comma == 1)
                {
                    out += ", ";
                }
                else if(!comma)
                {
                    out += "\n";
                }
            }
            else
            {
                if(x < 7)
                {
                    out += constTmp + " " + titleTmp;
                    if(constTmp != "@invis@" || titleTmp != "@invis@")
                    {
                        out += "\n";
                    }

                }
                else if(x == 7)
                {
                    if(!isInteger(titleTmp))
                    {
                        // If title8 is not an integer, treat it like a regular title.
                        if(constTmp != "@invis@")
                        {
                            out += constTmp + " " + titleTmp;
                        }
                        else
                        {
                            out += titleTmp;
                        }
                    }
                    else
                    {
                        
                        if(constTmp != "@invis@")
                        {
                            out += constTmp + " " + titleTmp;
                        }
                        else
                        {
                            out += titleTmp;
                        }
                        // Otherwise, it's an integer so check for whether there's a percentage behind or a max value.'
                        if(llList2Integer(options, 5) == 1)
                        {
                            out += "%";
                        }
                        else if(llList2Integer(options, 5) == 2 && llList2Integer(options, 4) > 0)
                        {
                            out += "/"+llList2String(options, 4);
                        }
                    }
                }
            }
            ++x;
        } while (x<=7);
        if(factionRank != "null")
        {
            out = out + "\n<" + factionRank + ">";
        }
    }
    else
    {
        if(status == 1)
        {
            out = "{OOC}\n{"+llList2String(title, 0)+"}";
            if(ooc != "@invis@")
            {
                out += "\n[" + ooc + "]";
            }
       }
        else if(status == 2)
        {
            out = "{AFK}\n{"+llList2String(title, 0)+"}";
            if(afk != "@invis@")
            {
                out += "\n[" + afk + "]";
            }
        }
    }

    //llOwnerSay(out);
    // Parse all the tags and strip them out of the string as we update the SetText.
    llSetText(stripTags(parseTags(out)), rgb2sl((vector)colour), alpha);
    sendCharDetails();
    if(charid != 0)
    {
        llMessageLinked(LINK_THIS, 12, "timer", NULL_KEY); // If setTitle() has been triggered, inform the main script to do autosave checks.
    }
}
vector rgb2sl( vector rgb )
{
    return rgb / 255;
}

integer strIsVector(string str)
{
    str = llStringTrim(str, STRING_TRIM);

    if(llGetSubString(str, 0, 0) != "<" || llGetSubString(str, -1, -1) != ">")
        return FALSE;

    integer commaIndex = llSubStringIndex(str, ",");

    if(commaIndex == -1 || commaIndex == 1)
        return FALSE;

    if( !strIsDecimal(llGetSubString(str, 1, commaIndex - 1)) || llGetSubString(str, commaIndex - 1, commaIndex - 1) == " " )
        return FALSE;

    str = llDeleteSubString(str, 1, commaIndex);

    commaIndex = llSubStringIndex(str, ",");

    if(commaIndex == -1 || commaIndex == 1 || commaIndex == llStringLength(str) - 2 ||

        !strIsDecimal(llGetSubString(str, 1, commaIndex - 1)) || llGetSubString(str, commaIndex - 1, commaIndex - 1) == " " ||

        !strIsDecimal(llGetSubString(str, commaIndex + 1, -2)) ||  llGetSubString(str, -2, -2) == " ")

            return FALSE;

    return TRUE;
}

// Returns TRUE if the string is a decimal
integer strIsDecimal(string str)
{
    str = llStringTrim(str, STRING_TRIM);

    integer strLen = llStringLength(str);
    if(!strLen){return FALSE;}

    integer i;
    if(llGetSubString(str,0,0) == "-" && strLen > 1)
        i = 1;
    else
        i = 0;

    integer decimalPointFlag = FALSE;

    for(; i < strLen; i++)
    {
        string currentChar = llGetSubString(str, i, i);

        if(currentChar == ".")
            if(decimalPointFlag)
                return FALSE;
        else
            decimalPointFlag = TRUE;
        else if(currentChar != "3" && currentChar != "6" && currentChar != "9" &&
            currentChar != "2" && currentChar != "5" && currentChar != "8" && // the order dosen't matter
                currentChar != "1" && currentChar != "4" && currentChar != "7" && currentChar != "0")
                    return FALSE;
    }

    return TRUE;
}

string parseTags(string data)
{
    //////////////////////////////////////////////////////
    // PARSING FUNCTION
    //////////////////////////////////////////////////////
    list tmp;
    if(~llSubStringIndex(data, "$p"))
    {
        do {
            integer inx = llSubStringIndex(data, "$p");
            integer enx = (inx + 1);
            data = llDeleteSubString(data, inx, enx);
            if(!status) 
            {
                data = llInsertString(data, inx, "\n");
            }
        }
        while(~llSubStringIndex(data, "$p"));
    }
    // {perc}
    if(~llSubStringIndex(data, "{perc}"))
    {
        do {
        integer inx = llSubStringIndex(data, "{perc}");
        integer enx = (inx + 5);
        data = llDeleteSubString(data, inx, enx);
        data = llInsertString(data, inx, "%");
        }
        while(~llSubStringIndex(data, "{perc}"));
    }
    if(~llSubStringIndex(data, "@T@"))
    {
        do {
        integer inx = llSubStringIndex(data, "@T@");
        integer enx = (inx + 2);
        data = llDeleteSubString(data, inx, enx);
        }
        while(~llSubStringIndex(data, "@T@"));
    }
    if(~llSubStringIndex(data, "=>"))
    {
        do {
        integer inx = llSubStringIndex(data, "=>");
        integer enx = (inx + 1);
        data = llDeleteSubString(data, inx, enx);
        //data = llInsertString(data, inx, "%");
        }
        while(~llSubStringIndex(data, "=>"));
    }
    return llStringTrim(data, STRING_TRIM);
}

// Attempting to integrate tag stripping into one function.
string stripTags(string data)
{
    //////////////////////////////////////////////////////
    // STRIPPING FUNCTION
    //////////////////////////////////////////////////////
    while(~llSubStringIndex(data, "$"))
    {
        // We'll want to strip tags from the savefile names.
        // This loop runs while $ is still found in the string.

        // Find the first index of $ in the string.
        integer tagBeginning = llSubStringIndex(data, "$");

        // Then remove it.
        data = llDeleteSubString(data, tagBeginning, (tagBeginning + 1));

        // Rinse and repeat until loop returns false.
    }
    // Then do another loop, this one to hide invis tags.
    while(~llSubStringIndex(data, "@invis@"))
    {
        integer inx = llSubStringIndex(data, "@invis@");
        data = llStringTrim(llDeleteSubString(data, inx, (inx+6)), STRING_TRIM);
    }
    // Trim the string just in case we have preceding or succeeding whitespace.
    return llStringTrim(data, STRING_TRIM);

}

parseReturnValue(string body)
{
    //llOwnerSay(body);
    // Parse the return value of HTTP request based on numerous params.
    string function;
    integer func;
    integer char_id;
    if(~llSubStringIndex(body, "alltitles:charid="))
    {
        // Update all the titles.
        // "alltitles:charid=" . $result['character_id'] . ";" . $result['constants'] . "@T@" . $result['titles'] . "@T@" . $result['options'];
        function = "alltitles:charid=";
        integer num = llSubStringIndex(body, ":");
        body = llStringTrim(llDeleteSubString(body, 0, num), STRING_TRIM);
        if(!~llSubStringIndex(body, "alltitles"))
        {
            // If alltitles is gone, only charid should remain.
            if(~llSubStringIndex(body, "charid="))
            {
                // if charid= is present, sweet!
                // We already know how many letters that is, so let's find the :.
                num = llSubStringIndex(body, ":");
                if((integer)llGetSubString(body, 7, (num-1)))
                {
                    char_id = (integer)llGetSubString(body, 7, (num-1));
                    body = llStringTrim(llDeleteSubString(body, 0, num), STRING_TRIM);
                }
                else
                {
                    llOwnerSay("Couldn't get character ID from string. Body: " + body);
                    return;
                }
            }
        }
        else
        {
            llOwnerSay("alltitles is not gone. Please copy this message to customer support. String: " + body);
            return;
        }
            // If char_id is different from ID, we are loading a new character.
            // In this case, update char ID.
            // This should always be called when loading anyway.
            charid = char_id;
            list tmp = llParseString2List(body, ["@T@"], []);
            constant = llParseStringKeepNulls(
                llList2String(
                    tmp, 0
                        ), ["=>"], []
                            );
            title = llParseStringKeepNulls(
                llList2String(
                    tmp, 1
                        ), ["=>"], []
                            );
            list tmpOptions = llParseString2List(
                llList2String(
                    tmp, 2
                        ), ["=>"], []
                            );
            // Update options like this so it doesn't replace any additional parameters the tool expects.
            // If the retrieved list is longer than the stored one, it automatically appends the rest.
            options = llListReplaceList(options, tmpOptions, 0, (llGetListLength(tmpOptions) - 1));
            setText();
            llOwnerSay("Character \"" + stripTags(llList2String(title, 0)) + "\" has been successfully loaded.");
            llSay(squawker, "::readyUpdate::" + (string)llGetOwner());
            llSleep(0.3);
            llSay(squawker, "::doUpdate::" + (string)llGetOwner());
    }
    
    //llOwnerSay("Memory free: " + (string)llGetFreeMemory() + " kilobits");
}

sendCharDetails()
{
    llMessageLinked(LINK_THIS, 12, "charid:"+(string)charid+":charname:"+llList2String(title, 0), NULL_KEY); // Inform main script of character name.
    llMessageLinked(LINK_THIS, 1337, llList2String(title, 0), ""); // Informs chatter of name.
}

string formTooltip(list data, integer changeDialogStatus) { // Create a tooltip for the changetitle/constant dialogs.
    // Code
    string out;
    if(llGetListLength(data) == 8)
    {
        out = "Please select what to change:\n";
        integer x = 0;
        integer num;
        for(;x<=7;x++)
        {
            num = (x+1);
            if(changeDialogStatus == 1)
            {
                out += "Title " + (string)num + ": " + llList2String(title, x) + " (Const"+(string)num+": "+llList2String(constant, x)+")";
            }
            else if(changeDialogStatus == 2)
            {
                out += "Const " + (string)num + ": " + llList2String(constant, x);
            }
            if(x<7)
            {
                out += "\n";
            }
        }
    }
    else
    {
        // If we don't have 8 entries, error out!
        llOwnerSay("ERROR: Can't find 8 entries in the titles or constants list! Please contact support!");
    }
    return out;
}

list order_buttons(list buttons)
{
    return llList2List(buttons, -3, -1) + llList2List(buttons, -6, -4)
         + llList2List(buttons, -9, -7) + llList2List(buttons, -12, -10);
}

createList(integer changeDialogStatus) { // Create the list for the dialog box.
    // FALSE (0) = default, 1 = title, 2 = const
    list copy;
    string input;
    string tooltip;
    line = 0;
    changing = 0;
    if(changeDialogStatus == 1) {
        copy = title;
        input = "title";
    }
    else if(changeDialogStatus == 2) {
        copy = constant;
        input = "const";
    }
    else
    {
        // If none of the above are true, error out and return.
        llOwnerSay("ERROR: integer changeDialogStatus either false or higher than 2. Please contact support!");
        return;
    }
    
    // If no error occurs here, we are a-go!
    tooltip = formTooltip(copy, changeDialogStatus);
    copy = [];
    integer x = 0;
    for(;x<=7;x++)
    {
        copy += [input+(string)(x+1)];
    }
    llListenRemove(dialogHandler);
    dialogHandler = llListen(dialogChan, "", llGetOwner(), "");
    llDialog(llGetOwner(), tooltip, order_buttons(copy), dialogChan);
}

speakOut(string what, string message) {
    // Speak out the change to the titler when AFK or OOC.
    llOwnerSay("You have changed '"+what+"' to: "+message);
}

key kvpKey;

string kvpId(string d)
{
    return (string)llGetOwner()+"|"+d;
}

default
{
    state_entry()
    {
        // Init
        //setText();
        kvpKey = llReadKeyValue(kvpId("status"));
        hud_channel = Key2AppChan(llGetOwner(), 1337);
        dialogChan = Key2AppChan(llGetOwner(), 200);
        llListen(hud_channel, "", "", "");
        settingsHandler = llListen(settingsChan, "", "", "");
        squawker = Key2AppChan(llGetOwner(), 1338);
        llListen(squawker, "", "", "");
    }
    
    dataserver(key id, string body)
    {
        if(id == kvpKey)
        {
            if(llGetSubString(body, 0, 0) == "0")
            {
                // If we failed, add new KVP.
                kvpKey = llUpdateKeyValue(kvpId("status"), "ic", FALSE, "");
            }
            else
            {
                body = llGetSubString(body, 2, -1);
                if(body == "ooc")
                {
                    status = 1;
                }
                else if(body == "afk")
                {
                    status = 2;
                }
            }
        }
    }
    

    link_message(integer sender, integer num, string data, key id)
    {

        if(num == 1331)
        {
            // If 1331, we're getting a regen message.
            if(data == "regen")
            {
                // Regen logic here.
                if(llGetUnixTime() > (lastPostregen + postregenInterval))
                {
                    if(isInteger(llList2String(title, 7)))
                    {
                        integer num1 = llList2Integer(options, 3);
                        integer num2 = llList2Integer(title, 7);
                        num2 = num2 + num1;
                        if(num2 > llList2Integer(options, 4))
                        {
                            num2 = llList2Integer(options, 4);
                        }
                        title = llListReplaceList(title, [(string)num2], 7, 7);
                        lastPostregen = llGetUnixTime();
                        llMessageLinked(LINK_SET, 12, "timer", NULL_KEY);
                        setText();
                    }
                }
            }
        }
        else if(num == 13)
        {
            if(data == "update")
            {
                llMessageLinked(LINK_SET, 13, "&updall=1" + compileTitlerData(), NULL_KEY);
            }
        }
        else if(num == 14) // This means we're loading a character.
        {
            parseReturnValue(data);
        }
        else if(num == 15) // Messages sent to 15 are channel changes.
        {
            if(isInteger(data))
            {
                settingsChan = (integer)data;
                llListenRemove(settingsHandler);
                settingsHandler = llListen(settingsChan, "", "", "");
            }
        }
        else if(num == 16) // Faction Rank Data
        {
            if(data == "null")
            {
                factionRank = "null";
            }
            else
            {
                list tmp = llParseString2List(data, ["&&"], []);
                factionRank = llList2String(tmp, 1) + " " + llList2String(tmp, 2);
            }
            setText(); // Then update titler.
            // This is a pretty dumb way to do it, but I don't want to fuck around with
            // this code more than I have to. It's ancient and I have very little memory
            // of how the RP tool works!
        }

    }
    
    listen(integer chan, string name, key id, string message)
    {
        if(llGetOwnerKey(id) == llGetOwner() && chan == squawker)
        {
            // Squawk things!
            if(message == "getid")
            {
                llSay(squawker, "charid:"+(string)charid+":"+llList2String(title,0));
            }
        }
        if(llGetOwner() == id || (llGetOwnerKey(id) == llGetOwner() && llList2String(llGetObjectDetails(id, [OBJECT_DESC]), 0) == "rptool-hud"))
        {
            if((chan == settingsChan || chan == hud_channel) && charid != 0)
            {
               if(llGetSubString(llToLower(message), 0, 4) == "title" && isInteger(llGetSubString(llToLower(message), 5, 5)))
                {
                    integer num = (integer)llGetSubString(message, 5, 5);
                    num = (num - 1);
                    message = llStringTrim(llDeleteSubString(message, 0, 5), STRING_TRIM);
                    if(llToLower(message) == "hide" || llToLower(message) == "none")
                    {
                        message = "@invis@";
                    } else if(message == "")
                    {
                        llOwnerSay("Invalid string; This error occurs because input was all whitespace. Terminated.");
                        return;
                    }
                    title = llListReplaceList(title, [(string)parseTags(message)], num, num);
                     // Set a timer event, at the end of which we'll send updates to the server.
                    // This timer prevents user spam.
                    setText();
                    if(status) {
                        // If status is set, speak out!
                        speakOut("title"+(string)(num+1), message);
                    }
                }
                else if(llGetSubString(llToLower(message), 0, 4) == "const" && isInteger(llGetSubString(llToLower(message), 5, 5)))
                {
                    integer num = (integer)llGetSubString(message, 5, 5);
                    num = (num - 1);
                    message = llStringTrim(llDeleteSubString(message, 0, 5), STRING_TRIM);
                    if(llToLower(message) == "hide" || llToLower(message) == "none")
                    {
                        message = "@invis@";
                    } else if(message == "")
                    {
                        llOwnerSay("Invalid string; This error occurs because input was all whitespace. Terminated.");
                        return;
                    }
                    constant = llListReplaceList(constant, [(string)parseTags(message)], num, num);
                    setText();
                    if(status) {
                        // If status is set, speak out!
                        speakOut("const"+(string)(num+1), message);
                    }

                }
                else if(llToLower(message) == "comma")
                {
                
                    // Toggle commas.
                    integer comma = llList2Integer(options, 2); // 0 = newline separation, 1 = comma separation, 2 = whitespace separation
                    if(comma >= 2)
                    {
                        comma = 0;
                    }
                    else
                    {
                        ++comma;
                    }
                    options = llListReplaceList(options, [comma], 2, 2);
                    if(status)
                    {
                        llOwnerSay("Comma mode set to: " + (string)comma);
                    }
                    setText();
                }
                else if(llGetSubString(llToLower(message), 0, 8) == "postregen")
                {
                    list tmp = llParseString2List(message, [" "], []);
                    if(llGetListLength(tmp) == 3)
                    {
                        // If list length has exactly 3 entries, do the thing. Otherwise do nothing and return error.
                        string one = llList2String(tmp, 1); // Regen rate.
                        string two = llList2String(tmp, 2); // Regen max.
                        if(isInteger(one) && isInteger(two))
                        {
                            options = llListReplaceList(options, [(integer)one, (integer)two], 3, 4);
                            llOwnerSay("Regen rate set to " + one + " per post; Maximum energy set to " + two + ".");
                            setText();
                        }
                        else
                        {
                            llOwnerSay("ERROR: Invalid postregen. Example of expected input: /" + (string)settingsChan + " postregen 5 100. Your input: /"+(string)settingsChan+" "+message);
                        }
                    }
                    else
                    {
                        llOwnerSay("ERROR: Postregen syntax error. Too many or too few values. Expected two. Example input: /" + (string)settingsChan + " postregen 5 100. Your input: /"+(string)settingsChan+" "+message);
                    }
                }
                else if(llToLower(message) == "percent")
                {
                    // Toggle percent status.
                    integer percent = llList2Integer(options, 5);
                    if(percent >= 2)
                    {
                        percent = 0;
                    }
                    else
                    {
                        ++percent;
                    }
                    options = llListReplaceList(options, [percent], 5, 5);
                    string opt;
                    if(percent == 0)
                    {
                        opt = "show percent";
                    }
                    else if(percent == 1)
                    {
                        opt = "show maximum energy";
                    }
                    else if(percent == 2)
                    {
                         opt = "hide percent";
                    }
                    llOwnerSay("Percentage option on title8 set to option: " + opt);
                    setText();
                }
                else if(llGetSubString(llToLower(message), 0, 4) == "color")
                {
                    message = llDeleteSubString(message, 0, 4);
                    message = llStringTrim(message, STRING_TRIM);
                    string mTemp = message;
                    mTemp = llDumpList2String(llParseString2List(mTemp, [" "], []), ",");
                    if(strIsVector("<"+mTemp+">")) // If the colour value is indeed a vector...
                    {
                        options = llListReplaceList(options, [mTemp], 0, 0); // Set the value.
                        
                        setText(); // Reparse the title.
                    }
                    else if(~llListFindList(colors, [message]))
                    {
                        options = llListReplaceList(options, [llList2String(colors, (llListFindList(colors, [message])+1))], 0, 0); // Set the value.
                        
                        setText(); // Reparse the title.
                    }
                    else if(llToLower(message) == "random")
                    {
                        options = llListReplaceList(options, [(string)llFrand(255.0)+","+(string)llFrand(255.0)+","+(string)llFrand(255.0)], 0, 0); // Set the value.
                        
                        setText(); // Reparse the title.
                    }
                    else
                    {
                        llOwnerSay("Colour changing failed. Invalid vector, or colour preset doesn't exist.");
                    }
                }
                else if(llGetSubString(llToLower(message), 0, 2) == "afk")
                {
                    message = llStringTrim(llDeleteSubString(message, 0, 2), STRING_TRIM);
                    if(message != "" && message != " ")
                    {
                        if(llToLower(message) == "hide" || llToLower(message) == "none")
                        {
                            afk = "@invis@";
                        }
                        else
                        {
                            afk = message;
                        }
                    }
                    status = 2;
                    kvpKey = llUpdateKeyValue(kvpId("status"), "afk", FALSE, "");
                    setText();
                }
                else if(llGetSubString(llToLower(message), 0, 2) == "ooc")
                {
                    message = llStringTrim(llDeleteSubString(message, 0, 2), STRING_TRIM);
                    if(message != "" && message != " ")
                    {
                        if(llToLower(message) == "hide" || llToLower(message) == "none")
                        {
                            ooc = "@invis@";
                        }
                        else
                        {
                            ooc = message;
                        }
                    }
                    status = 1;
                    kvpKey = llUpdateKeyValue(kvpId("status"), "ooc", FALSE, "");
                    setText();
                }
                else if(llGetSubString(llToLower(message), 0, 1) == "ic" || llGetSubString(llToLower(message), 0, 3) == "back")
                {
                        status = 0;
                        kvpKey = llUpdateKeyValue(kvpId("status"), "ic", FALSE, "");
                        setText();
                }
                else if(llToLower(message) == "changetitle") { // Change titles in dialogs.
                    // Code here.
                    createList(1);
                }
                else if(llToLower(message) == "changeconstant") { // Change constants in dialogs.
                    // Code here.
                    createList(2);
                }
                else // If none of the above match then we're probably updating titles...
                {
                    // First let's just parse the entire string to a list, and then take the first value of that from it...
                    list msg = llList2List(llParseString2List(message, [" "], []), 0, 0); // What's our value here...
                    integer i = (llGetListLength(constant) - 1); // Get the length of our constants list.
                    integer x; // Our counting integer!
                    integer location = -1; // Where in the list we are...
                    string cnst; // Temporary string for constants.
                    if(~llSubStringIndex(llList2String(msg, 0), "_"))
                    {
                        // If there is underscore, treat as whitespace.
                        msg = llListReplaceList(msg, [llDumpList2String(llParseString2List(llList2String(msg, 0), ["_"], []), " ")], 0, 0);
                    }
                    for(x=0;x<=i;x++) // Begin the loop...
                    {
                        // Now we're going to find which location our title's at...
                        cnst = llToLower(llList2String(constant, x)); // Parse to string.
                        if(llGetSubString(cnst, -1, -1) == ":" && llGetSubString(llList2String(msg, 0), -1, -1) != ":") // If there's a colon there and it's not present in the command, we'll remove it.
                        {
                            cnst = llDeleteSubString(cnst, -1, -1);
                        }
                        if(cnst == llToLower(llList2String(msg, 0))) // If the constants match up exactly, we have our title!
                        {
                            location = x;
                            jump break;
                        }
                    }
                    @break;
                    if(location < 0) // If after the loop is done, we have no location...
                    {
                        //llOwnerSay("Couldn't find your title. Your command: "+command); // Error away!
                        return; // And exit.
                    }
                    // But if all went as planned...
                    // Then we're going to update a title. So let's update!

                    if(location > 7) // If tmp is bigger than 7...
                    {
                        llOwnerSay("Could not update title. Title number not valid.");
                        return;
                    }

                    message = llDeleteSubString(message, 0, (llStringLength(llList2String(msg, 0))-1)); // Then delete "title#" from the string.
                    message = llStringTrim(message, STRING_TRIM); // Trim the string for leading and trailing spaces.
                    //if(llStringLength(message) < 1)
                    if(message == "")
                    {
                        llOwnerSay("Invalid string; This error occurs because input was all whitespace. Terminated.");
                        return;
                    }
                    if(location == 7 && !isInteger(message)) // If we're updating our energy, and M is not an integer...
                    {
                        if(llToLower(message) == "hide" || llToLower(message) == "none")
                        {
                            message = "@invis@";
                        }
                        title = llListReplaceList(title, [(string)message], location, location);
                    }
                    else if(llToLower(message) == "hide" || llToLower(message) == "none")
                    {
                        message = "@invis@";
                    }
                    title = llListReplaceList(title, [(string)parseTags(message)], location, location);
                    
                    setText();
                    if(status) {
                        // If status is set, speak out!
                        speakOut(llList2String(constant, location), message);
                    }

                }
            }
            else if(chan == dialogChan) { // Dialog boxes!
                // Process dialogs!
                if(changing == 1) { // Title
                    if(line) { // If we have a line, do things!
                        // Change the title!
                        integer tmp = (line - 1);
                        if(llToLower(message) == "hide" || llToLower(message) == "none")
                        {
                            message = "@invis@";
                        } else if(message == "")
                        {
                            llOwnerSay("Invalid string; This error occurs because input was all whitespace. Terminated.");
                            return;
                        }
                        if(tmp == 0) // If we're changing the name, catch trailing newlines and delete them.
                        {
                            string escaped = llEscapeURL(message);
                            if(~llSubStringIndex(escaped, "%0A"))
                            {
                                while(~llSubStringIndex(escaped, "%0A"))
                                {
                                    integer escape = llSubStringIndex(escaped, "%0A");
                                    // Filter out linebreaks from the end of the textbox input when inserting a name.
                                    escaped = llDeleteSubString(escaped, escape, (escape + 2));
                                }
                                message = llUnescapeURL(escaped);
                            }
                        }
                        title = llListReplaceList(title, [(string)message], tmp, tmp);
                        if(status)
                        {
                            speakOut("title"+(string)(tmp + 1), (string)message);
                        }
                    }
                    llListenRemove(dialogHandler);
                    line = FALSE;
                    setText();
                }
                else if(changing == 2) { // Constant
                    if(line) { // If we have a line, do things!
                        // Change the title!
                        integer tmp = (line - 1);
                        if(llToLower(message) == "hide" || llToLower(message) == "none")
                        {
                            message = "@invis@";
                        } else if(message == "")
                        {
                            llOwnerSay("Invalid string; This error occurs because input was all whitespace. Terminated.");
                            return;
                        }
                        constant = llListReplaceList(constant, [(string)message], tmp, tmp);
                        if(status)
                        {
                            speakOut("const"+(string)(tmp + 1), (string)message);
                        }
                    }
                    llListenRemove(dialogHandler);
                    line = FALSE;
                    setText();
                }
                else if(~llSubStringIndex(message, "title")) {
                    changing = 1;
                    line = (integer)llGetSubString(message, -1, -1);
                    // Open TextBox.
                    llTextBox(llGetOwner(), "Change " + message + " to: ", dialogChan);
                }
                else if(~llSubStringIndex(message, "const")) {
                    changing = 2;
                    line = (integer)llGetSubString(message, -1, -1);
                    // Open TextBox.
                    llTextBox(llGetOwner(), "Change " + message + " to: ", dialogChan);
                }
            }
        }
    }
}
