integer Key2AppChan(key ID, integer App) { // Generate chat channel.
    return 0x80000000 | ((integer)("0x"+(string)ID) ^ App);
}
integer linkchan;
default
{
    state_entry()
    {
        linkchan = Key2AppChan(llGetOwner(), 9512);
    }
    changed(integer c)
    {
        if(c & CHANGED_OWNER)
        {
            llResetScript();
        }
    }
    touch_end(integer total_number)
    {
        llMessageLinked(LINK_SET, linkchan, (string)llDetectedLinkNumber(0), "");
    }
    attach(key id)
    {
        if(llGetAttached())
        {
            llResetScript();
        }
    }
}
