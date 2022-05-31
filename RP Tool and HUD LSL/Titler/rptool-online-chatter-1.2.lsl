// Neckbeard Chatter
// Handles chats.
//
// Last edit: September 25th, 2019
//
// Search "#STOP" to find where I last stopped working.
/*
    Changes July 30th:
    - Added means of detecting special characters in a name.
    - Added workaround for special characters in a name displaying as ?? in the chat.
    
    Changes August 31st:
    - Added more items to allowedSpecials.
    
    Changes September 25th:
    - Added more items to allowedSpecials.
*/


string curName;

list allowedSpecials = ["-","'",".",",",":",";"];

integer chk_pure_ASCII(string data) // Look for special characters in the name.
{
    data = llDumpList2String(llParseStringKeepNulls((data = "") + data, [" "] + allowedSpecials, []), "");
    if(data != llEscapeURL(data))
    {
        return FALSE;
    }
    return TRUE;
}

integer specialName; // TRUE if name contains special characters.

integer togglename;
integer whisper;

integer chan = 1;

string funcName() // Outputs processed name.
{
    string name = curName;
    if(togglename)
    {
        if(~llSubStringIndex(name, "$n"))
        {
            // If a name tag is in use, shorten until the end of the name tag.
            // Ignore all other name tags.
            name = llStringTrim(llList2String(llParseString2List(name, ["$n"], []), 0), STRING_TRIM);
        }
        else
        {
            name = llStringTrim(llList2String(llParseString2List(name, [" "], []), 0), STRING_TRIM);
        }
    }
    else if(~llSubStringIndex(name, "$n"))
    {
        integer inx = llSubStringIndex(name, "$n");
        name = llStringTrim(llDeleteSubString(name, inx, (inx + 1)), STRING_TRIM);
    }
   
   return stripTags(name); // Strip tags not caught by the previous function.
}

string stripTags(string data)
{
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
    // Trim the string just in case we have preceding or succeeding whitespace.
    return llStringTrim(data, STRING_TRIM);

}

// Function for speaking.
funcDoSpeak(string post)
{
    string savedName = llGetObjectName();
    //llOwnerSay((string)llGetUsedMemory()+" bytes used.");
    // Check to see if a channel has been typed in double by accident.
    if(llGetSubString(post, 0, 0) == "/" && (string)((integer)llGetSubString(post, 1, 1)) == llGetSubString(post, 1, 1))
    {
        post = llDeleteSubString(post, 0, 1);
        post = llStringTrim(post, STRING_TRIM);
    }
    // First handle OOC chat. With double brackets, we're indicating OOC!
    if(llGetSubString(post, 0, 1) == "((" && llGetSubString(post, (-1 -1), -1) == "))")
    {
        post = llDeleteSubString(post, 0, 1); // Delete first double brackets.
        post = llDeleteSubString(post, (-1 - 1), -1); // Delete second double brackets.
        post = llStringTrim(post, STRING_TRIM); // Trim leading and trailing spaces.
        if(specialName)
        {
            llSetObjectName("");
            post = curName + " ("+llKey2Name(llGetOwner())+") OOC: " + post;
        }
        else
        {
            llSetObjectName(curName+" ("+llKey2Name(llGetOwner())+") OOC");
        }
        llSay(0, post);
    }
    else if(llGetSubString(post, 0, 0) == "#") // If we begin with a hashtag, we're whispering!
    {
        post = llDeleteSubString(post, 0, 0);
        post = llStringTrim(post, STRING_TRIM);
        if(specialName)
        {
            llSetObjectName("");
            post = funcName() + " whispers, \"" + post + "\"";
            llWhisper(0, "/me " + post);
        }
        else
        {
            llSetObjectName(funcName());
            llWhisper(0, "/me whispers, \""+post+"\"");
        }
    }
    else if(llGetSubString(post, 0, 0) == "!") // Shout if we have an exclamation mark.
    {
        post = llDeleteSubString(post, 0, 0);
        post = llStringTrim(post, STRING_TRIM);
        if(specialName)
        {
            llSetObjectName("");
            post = funcName() + ": " + post;
            llShout(0, post);
        }
        else
        {
            llSetObjectName(funcName());
            llShout(0, post);
        }
    }
    else if(llGetSubString(post, 0, 0) == ":") // Talk if we have a colon.
    {
        post = llDeleteSubString(post, 0, 0);
        post = llStringTrim(post, STRING_TRIM);
        if(specialName)
        {
            post = funcName() + " says, \"" + post + "\"";
            llSetObjectName("");
            if(!whisper)
            {
                llSay(0, post);
            }
            else
            {
                llWhisper(0, post);
            }
        }
        else
        {
            llSetObjectName(funcName());
            if(!whisper)
            {
                llSay(0, "/me says, \""+post+"\"");
            }
            else
            {
                llWhisper(0,"/me says, \""+post+"\"");
            }
        }
    }
    else if(llGetSubString(post, 0, 0) == "'")
    {
        string charName = funcName();
        if(llGetSubString(llToLower(post), 0, 1) == "'s" || llGetSubString(llToLower(post), 0, 0) == "'")
        {
            if(llToLower(llGetSubString(charName, -1, -1)) == "s")
            {
                if(specialName)
                {
                    llSetObjectName("");
                    if(llGetSubString(post, 1, 1) == " " || llGetSubString(post, 1, 1) == "")
                    {
                        post = llStringTrim(llDeleteSubString(post, 0, 0), STRING_TRIM);
                    }
                    else
                    {
                        post = llStringTrim(llDeleteSubString(post, 0, 1), STRING_TRIM);
                    }
                    post = charName + "' " + post;
                }
                else
                {
                    llSetObjectName(charName+"'");
                    if(llGetSubString(post, 1, 1) == " " || llGetSubString(post, 1, 1) == "")
                    {
                        post = llStringTrim(llDeleteSubString(post, 0, 0), STRING_TRIM);
                    }
                    else
                    {
                        post = llStringTrim(llDeleteSubString(post, 0, 1), STRING_TRIM);
                    }
                }

            }
            else
            {
                if(specialName)
                {
                    llSetObjectName("");
                    post = charName+"'s "+llStringTrim(llDeleteSubString(post, 0, 1), STRING_TRIM);
                }
                else
                {
                    llSetObjectName(charName+"'s");
                    post = llStringTrim(llDeleteSubString(post, 0, 1), STRING_TRIM);
                }

            }
        }
        else
        {
            if(specialName)
            {
                llSetObjectName("");
                post = charName + " " + post;
            }
            else
            {
                llSetObjectName(charName);
            }
        }
        if((post == " " || post == "") || ((llStringTrim(post, STRING_TRIM) == charName + "'s" || post == charName + "'") && specialName))
        {
            jump failed;
        }

        if(!whisper)
        {
            
            llSay(0, "/me "+post);
            
        }
        else
        {
            
            llWhisper(0,"/me "+ post);
            
        }

    }
    else if(llGetSubString(post, 0, 0) == ",")
    {
        post = llDeleteSubString(post, 0, 0);
        post = llStringTrim(post, STRING_TRIM);
        if(specialName)
        {
            llSetObjectName("");
            post = funcName() + ", " + post;
        }
        else
        {
            llSetObjectName(funcName()+",");
        }
        
        if((post == " " || post == "") || ((llStringTrim(post, STRING_TRIM) == funcName() + ",") && specialName))
        {
            jump failed;
        }
        if(!whisper)
        {
            llSay(0, "/me "+post);
        }
        else
        {
            llWhisper(0,"/me "+ post);
        }
    }
    else // If none of the above are true, chat normally!
    {
        if(specialName)
        {
            llSetObjectName("");
            post = llStringTrim(funcName() + " " + post, STRING_TRIM);
        }
        else
        {
            llSetObjectName(funcName());
        }
        if(!whisper)
        {
            llSay(0, "/me "+post);
        }
        else
        {
            llWhisper(0,"/me "+ post);
        }
    }
    llMessageLinked(LINK_THIS, 1331, "regen", NULL_KEY);
    @failed;
    llSetObjectName(savedName); // At the end of the chat, set object name back to default.

}

integer cHan;

integer hud_channel;
integer Key2AppChan(key ID, integer App) {
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}
key kvpChat;
string kvpId(string d)
{
    return (string)llGetOwner()+"|"+d;
}
default
{
    state_entry()
    {
        kvpChat = llReadKeyValue(kvpId("togglename"));
        llListen(4, "", llGetOwner(), "");
        llListen(3, "", llGetOwner(), "");
        llListen(22, "", llGetOwner(), "");
        cHan = llListen(chan, "", NULL_KEY, "");
        hud_channel = Key2AppChan(llGetOwner(), 1337);
        llListen(hud_channel, "", "", "");
    }
    dataserver(key id, string body)
    {
        if(id == kvpChat)
        {
            if(llGetSubString(body, 0, 0) == "0")
            {
                // If we failed, add new KVP.
                kvpChat = llUpdateKeyValue(kvpId("togglename"), "false", FALSE, "");
            }
            else
            {
                body = llGetSubString(body, 2, -1);
                if(body == "true")
                {
                    togglename = TRUE;
                }
                else if(body == "false")
                {
                    togglename = FALSE;
                }
            }
        }
    }
    
    on_rez(integer start_param)
    {
        if(whisper)
        {
            llOwnerSay("You are currently in 'whisper mode'. Your /4 and /3 chat range is 10m. Do '/1 chatrange' without quotes to revert this.");
        }
    }

    // Link message event for receiving currently loaded character names.
    // Responds to number 1337.
    link_message(integer sender, integer num, string m, key id)
    {
        if(num == 1337)
        {
            string tmp = m;
            curName = tmp; // Set the current name.
            if(!chk_pure_ASCII(funcName()))
            {
              specialName = TRUE;
            }
            else
            {
                specialName = FALSE;
            }
            //llOwnerSay(tmp + " -> " + curName);
        }
        else if(num == 15)
        {
            // Handle which channel we operate on.
            llListenRemove(cHan);
            chan = (integer)m;
            cHan = llListen(chan, "", NULL_KEY, "");
        }
        
    }
    
    // Obvious. This event is for when something has changed.
    changed(integer change)
    {
        // If the object has changed owners, we'll want to reset the script.
        if(change & CHANGED_OWNER)
        {
            // Obvious.
            llResetScript();
            
            // This is done to prevent the script from failing to recognize its new owner when attempting to
            // use the new chatter.
        }
    }
    
    
    listen(integer c, string n, key id, string m)
    {
        //llOwnerSay("Chatter HUD channel is: " + (string)hud_channel);
        //llOwnerSay("Chatter received on channel " + (string)c + ": " + m);
        // If id isn't the owner, exit.
        if(llGetOwner() == id || (llGetOwnerKey(id) == llGetOwner() && llList2String(llGetObjectDetails(id, [OBJECT_DESC]), 0) == "rptool-hud"))
        {

            if(c == 4)
            {
                if(m != "" && m != " ")
                {
                    funcDoSpeak(m); // Post if c == 4.
                }
            }
            // If we use OOC chat...
            else if(c == 22)
            {
                
                if(m == "" && m == " ") {
                    return;
                }
                // Talk OOC!
                string tmp = llGetObjectName();
                string tmpNameOoc;
                if(~llSubStringIndex(curName, "$n"))
                {
                    tmpNameOoc = llStringTrim(llDeleteSubString(curName, llSubStringIndex(curName, "$n"), (llSubStringIndex(curName, "$n") + 1)), STRING_TRIM);
                }
                else
                {
                    tmpNameOoc = curName;
                }
                if(specialName)
                {
                    llSetObjectName("");
                    m = llStringTrim(tmpNameOoc+" ("+llKey2Name(llGetOwner())+") OOC: " + m, STRING_TRIM);
                }
                else 
                {
                    llSetObjectName(tmpNameOoc+" ("+llKey2Name(llGetOwner())+") OOC");
                }
                llSay(0, m);
                llSetObjectName(tmp); // At the end of the chat, set object name back to default.
            }
            else if(c == 3) // NPC/Nameless speak!
            {
                if(m == "" && m == " ") {
                    return;
                }
                string tmp = llGetObjectName();
                // Check to see if a channel has been typed in double by accident.
                if(llGetSubString(m, 0, 0) == "/" && (string)((integer)llGetSubString(m, 1, 1)) == llGetSubString(m, 1, 1))
                {
                    m = llDeleteSubString(m, 0, 1);
                    m = llStringTrim(m, STRING_TRIM);
                }
                llSetObjectName("");
    
                if(llGetSubString(m, 0, 0) == "#") // If we begin with a hashtag, we're whispering!
                {
    
                    m = llDeleteSubString(m, 0, 0);
                    m = llStringTrim(m, STRING_TRIM);
                    llWhisper(0, "/me "+m);
                }
                else if(llGetSubString(m, 0, 0) == "!") // Shout if we have an exclamation mark.
                {
                    llSetObjectName("(player event)");
                    m = llDeleteSubString(m, 0, 0);
                    m = llStringTrim(m, STRING_TRIM);
                    llShout(0, m);
                }
    
                else // If none of the above are true, chat normally!
                {
                    if(!whisper)
                    {
                        llSay(0, "/me "+m);
                    }
                    else
                    {
                        llWhisper(0, "/me "+m);
                    }
                }
                llSetObjectName(tmp);
                llMessageLinked(LINK_THIS, 1331, "regen", NULL_KEY);
            }
            else if((c == chan || c == hud_channel))
            {
                if(llToLower(m) == "togglename") // Handles toggling of name display.
                {
                    if(togglename)
                    {
                        //togglename = FALSE;
                        kvpChat = llUpdateKeyValue(kvpId("togglename"), "false", FALSE, "");
                        llOwnerSay("Chatter now uses your characters' full names.");
                    }
                    else
                    {
                        //togglename = TRUE;
                        kvpChat = llUpdateKeyValue(kvpId("togglename"), "true", FALSE, "");
                        llOwnerSay("Chatter is now using only your characters' first names.");
                    }
                }
                else if(llToLower(m) == "togglewhisper" || llToLower(m) == "chatrange")
                {
                    if(!whisper)
                    {
                        whisper = TRUE;
                        llOwnerSay("/4 & /3 chatrange reduced to 10 meters.");
                    }
                    else
                    {
                        whisper = FALSE;
                        llOwnerSay("/4 & /3 chatrange increased to 20 meters.");
                    }
    
                }
            }
        }
        
    }

}
