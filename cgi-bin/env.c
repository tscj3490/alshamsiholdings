#include <stdio.h>
#include <time.h>

/* Use Perl. Perl is prettier, easier to follow, and more accepted as the 
standard for CGI development. The purpose of this code is to demonstrate
that compiled C works; and to show you how bad C code looks compared to
the Perl equivalent. (Compare with env.pl) */

extern char **environ;

main() {

    time_t now;
    time(&now);

    /* Make sure SCRIPT_NAME exists; otherwise we'll core dump later */

    if (!getenv("SCRIPT_NAME")) {
        printf("Content-type: text/plain\n\nError! SCRIPT_NAME isn't set!\n");
        exit(1);
    }

    /* Print the required Content-type header, and start printing HTML */

    printf("Content-type: text/html\n\n");
    printf("<HTML>\n");
    printf("<HEAD>\n");
    printf("<TITLE>Environment variables</TITLE>\n");
    printf("</HEAD>\n");
    printf("<BODY>\n");
    printf("<FONT SIZE=+2><B>Environment variables</B></FONT><BR>\n");

    printf("The following is a list of environment variables which are provided by the webserver.<BR>\n");
    printf("<BR>\n");
    printf("In Perl, these variables are available via <CODE>$ENV{'key'}</CODE>;\n");
    printf("in C, they are available using <CODE>getenv()</CODE><BR>\n");
    printf("<BR>\n");
    printf("For a detailed discussion about the CGI/1.1 interface, please visit the \n");
    printf("<A HREF=\"http://hoohoo.ncsa.uiuc.edu/cgi/\">CGI/1.1 specification</A>.<BR>\n");
    printf("<BR>\n");

    printf("<TABLE BORDER=0 BGCOLOR=#ffffc1 CELLSPACING=0 CELLPADDING=3>\n");
    printf("<TR><TD BGCOLOR=#0000b4><FONT COLOR=#ffffff><B>Key</B></FONT></TD><TD BGCOLOR=#0000b4><FONT COLOR=#ffffff><B>Value</B></FONT></TD></TR>\n");

    /* Output the environment variables. Sorry; I'm not wasting time sorting
    the output; that's not very fun to do in C. Seasoned C programmers will
    probably laugh here, but it gets the job done */

    if (environ) {
        int i = 0;
        char *poseq;

        while (environ[i] && (poseq = (char *) strchr(environ[i], '='))) {
            char *pos = environ[i];

            printf("<TR><TD>");
            while (pos != poseq) {
                putchar(*pos);
                pos++;
            }
            pos++;
            if (*pos == NULL) {
                printf("</TD><TD>&nbsp;</TD></TR>\n");
            } else {
                printf("</TD><TD>%s</TD></TR>\n", pos);
            }
            i++;
        }
    }

    /* Finish up the HTML */

    printf("</TABLE>\n");
    printf("<BR>\n");
    printf("<FONT SIZE=-1><I><A HREF=\"%s\">This script</A> ran at %s", getenv("SCRIPT_NAME"), ctime(&now));
    printf("as user %d</I></FONT>\n", (int) getuid());
    printf("</BODY>\n");
    printf("</HTML>\n");
}
