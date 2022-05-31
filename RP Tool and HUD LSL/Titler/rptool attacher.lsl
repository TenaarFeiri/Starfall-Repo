integer where = ATTACH_HEAD;
integer attaching = FALSE;
string hudName = "Neckbeard RP Tool Online HUD";
integer chan;
key usr;
integer time = 120;
integer detach = 5;
integer key2AppChan(key ID, integer App) { // Generate chat channel.
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}
integer killChan;
integer func_getHeight(key who)
{
    vector pos = (vector)llList2String(llGetObjectDetails(who, [OBJECT_POS]), 0);
    float z = (integer)pos.z;
    if((z-min)*(max-z) >= 0)
    {
        return TRUE;
    }
    return FALSE;
}
integer min = 1000;
integer max = 3500;
integer num;
integer Key2Chan(key ID) {
    return 0x80000000 | (integer)("0x"+(string)ID);
}
default
{
    state_entry()
    {
        //
        if(!llGetAttached())
        {
            return;
        }
        killChan = key2AppChan(llGetOwner(), where);
        llListen(killChan, "", "", "");
        llSetTimerEvent(detach);
        llSetLinkAlpha(LINK_THIS, -1, ALL_SIDES);
    }
    
    timer()
    {
        if(!llGetAttached())
        {
            llDie();
        }
        if(!func_getHeight(llGetOwner()))
        {
            llRequestPermissions(llGetOwner(), PERMISSION_ATTACH);
        }
        
    }

    on_rez(integer start)
    {
        if(llGetAttached())
        {
            return;
        }
        llListenRemove(chan);
        num = Key2Chan(llGetKey());
        chan = llListen(-8666, "", "", "");
        llSetTimerEvent(time);
        llRegionSay(-8666, "get:"+(string)start);
    }
    
    listen(integer c, string n, key id, string m)
    {
        if(c != killChan)
        {
            if(~llSubStringIndex(m, "give:"))
            {
                list tmp = llParseString2List(m, [":"], []);
                if(llGetAgentSize((key)llList2String(tmp, 1)) != ZERO_VECTOR)
                {
                    usr = (key)llList2String(tmp, 1);
                    llRequestExperiencePermissions(usr, "");
                }
            }
            else if(~llSubStringIndex(m, "hudrequest:"))
            {
                list tmp = llParseString2List(m, [":"], []);
                if(llList2Integer(tmp, 1) == num)
                {
                    llRegionSay(-8666, (string)num+":"+(string)usr);
                    attaching = TRUE;
                    llRequestExperiencePermissions(usr, "");
                }
            }
        }
        else
        {
            if(m == ":::killrptool:::" && llGetOwnerKey(id) == llGetOwner())
            {
                llOwnerSay("Duplicate HUD detaching.");
                llRequestPermissions(llGetOwner(), PERMISSION_ATTACH);
            }
        }
    }
    
    changed(integer change)
    {
        if(change & CHANGED_REGION)
        {
            llRequestPermissions(llGetOwner(), PERMISSION_ATTACH);
        }
    }
    
    run_time_permissions(integer perm)
    {
        if(perm & PERMISSION_ATTACH)
        {
            llDetachFromAvatar();
        }
    }
    
    attach(key agent)
    {
        if(llGetAttached())
        {
            killChan = key2AppChan(llGetOwner(), where);
            llListen(killChan, "", "", "");
            llSay(killChan, ":::killrptool:::");
            llResetScript();
        }
        else
        {
            llDie();
        }
    }
    
    experience_permissions(key agent)
    {
        if(!llGetAttached())
        {
            if(attaching)
            {
                llAttachToAvatarTemp(where);
            }
            else
            {
                llRezObject(hudName, llGetPos() + <0.0,0.0,0.0>, <0.0,0.0,0.0>, <0.0,0.0,0.0,0.0>, num);
            }
        }
    }
    
    experience_permissions_denied(key agent, integer reason)
    {
        llDie();
    }
}
