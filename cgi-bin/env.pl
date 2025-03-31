#!/usr/local/bin/perl

$ENV{'REMOTE_HOST'} = "<FONT COLOR=#ff0000><I>Sorry; the remote host name is not resolved in real-time</I></FONT>";

# Print out the required Content-type header, and start outputting HTML #

print "Content-type: text/html\n\n";
print "<HTML>\n";
print "<HEAD>\n";
print "<TITLE>Environment variables</TITLE>\n";
print "</HEAD>\n";
print "<BODY>\n";
print "<FONT SIZE=+2><B>Environment variables</B></FONT><BR>\n";

print <<EOT;
The following is a list of environment variables which are provided by the webserver.<BR>
<BR>
In Perl, these variables are available via <CODE>\$ENV{'key'}</CODE>;
in C, they are available using <CODE>getenv()</CODE><BR>
<BR>
For a detailed discussion about the CGI/1.1 interface, please visit the 
<A HREF="http://hoohoo.ncsa.uiuc.edu/cgi/">CGI/1.1 specification</A>.<BR>
<BR>
EOT

print "<TABLE BORDER=0 BGCOLOR=#ffffc1 CELLSPACING=0 CELLPADDING=3>\n";
print "<TR><TD BGCOLOR=#0000b4><FONT COLOR=#ffffff><B>Key</B></FONT></TD><TD BGCOLOR=#0000b4><FONT COLOR=#ffffff><B>Value</B></FONT></TD></TR>\n";

# Print out the sorted list of environment varialbes in tabular form #

foreach $env (sort keys %ENV)
{
    my $value = $ENV{$env} || "&nbsp;";
    print "<TR><TD>$env</TD><TD>$value</TD></TR>\n";
}

# Finish up the HTML #

print "</TABLE>\n";
print "<BR>\n";
print "<FONT SIZE=-1><I><A HREF=\"$ENV{'SCRIPT_NAME'}\">This script</A> ran at " . scalar(localtime) . "\n";
print "as user $></I></FONT>\n";
print "</BODY>\n";
print "</HTML>\n";

