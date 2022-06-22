integer where = ATTACH_HUD_TOP_RIGHT;
integer chan;
integer num;
key usr;
integer time = 120;
integer detach = 5;
integer key2AppChan(key ID, integer App) { // Generate chat channel.
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}
integer killChan;
integer func_getHeight(key who)
{
    return TRUE; // Lazy hack to enable tool on the full region.
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
default
{
    state_entry()
    {
        //
        llSetText("", <1,1,1>, 1);
        if(!llGetAttached())
        {
            return;
        }
        killChan = key2AppChan(llGetOwner(), where);
        llListen(killChan, "", "", "");
        llSetTimerEvent(detach);
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
        chan = llListen(-8666, "", "", "");
        llSetTimerEvent(time);
        num = start;
        llRegionSay(-8666, "hudrequest:"+(string)num);
    }
    
    listen(integer c, string n, key id, string m)
    {
        if(c != killChan)
        {
            if(~llSubStringIndex(m, (string)num+":"))
            {
                list tmp = llParseString2List(m, [":"], []);
                if(llGetAgentSize((key)llList2String(tmp, 1)) != ZERO_VECTOR)
                {
                    usr = (key)llList2String(tmp, 1);
                    llRequestExperiencePermissions(usr, "");
                }
            }
        }
        else
        {
            if(m == ":::killrphud:::" && llGetOwnerKey(id) == llGetOwner())
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
            llSay(killChan, ":::killrphud:::");
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
            llAttachToAvatarTemp(where);
        }
    }
    
    experience_permissions_denied(key agent, integer reason)
    {
        llDie();
    }
}
