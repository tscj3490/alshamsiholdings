#!/usr/local/bin/perl
# search_form.cgi -- cgi compliant ICE search interface
#
# ICE Version 1.5 beta 3 rev1
# September 1998
# (C) Christian Neuss (ice@isa.informatik.th-darmstadt.de)
# Modified by Chris Samaritoni
# Jan 1999

#--- start of configuration --- put your changes here ---

# NOTE: $ENV{'DOCUMENT_ROOT'} contains the full path to your web documents.

# Title or name of your server:
#   Example: local($title)="Search this server";
local($title)="Search this server";

# Header for the HTML output. "###TITLE###" will be replaced with dynamic text
# (ie. "Query results" or "Error in script").
$header = <<__EOT__;
<HTML>
<HEAD><TITLE>###TITLE###</TITLE></HEAD>
<BODY>
<H1>###TITLE###</H1>

__EOT__

# Footer for the HTML output

$footer = <<__EOT__;

<HR>
<I>This searchable archive was implemented with the
<A HREF="http://www.informatik.tu-darmstadt.de/~neuss/ice/ice.html">
ICE search engine</A> (boolean patch pj9711)</I>
</BODY>
</HTML>

__EOT__

# Location of the indexfile:
#   Note: under Windows or Windows NT, add the drive letter. Make sure
#   this the same as in search_index.cg
#   Example: $indexfile="$ENV{'DOCUMENT_ROOT'}/cgi-bin/index.idx";
$indexfile="$ENV{'DOCUMENT_ROOT'}/cgi-bin/index.idx";

# The Document Root is the directory where the "top level"
# documents reside.
#   Example
#   $docroot = "$ENV{'DOCUMENT_ROOT'}"
$docroot = $ENV{'DOCUMENT_ROOT'};

# Maximum number of hits to return
# Example:
#   $MAXHITS=100;
$MAXHITS=50;

# Minimum length of word to be indexed (same as in search_index.cgi)
# Example:
#   $MINLEN=3;
$MINLEN=3;

#--- end of configuration --- you don't have to change anything below ---

local(@errStack);

# do the real work, but trap any errors
eval '&main';

# if an error has occured, log it to stdout
if($@){
  &send_header("Error in Script"); # just in case
  print "$@\n</BODY>";
}

# main loop
sub main {
  # if this script is called up "by hand", run a test
  unless($ENV{"SCRIPT_NAME"}){
    if($ARGV[0] eq "-i"){
      print &buildIndex();
    }
    local($word) = ($#ARGV==-1) ? "test" : join(" ",@ARGV) ;
    print &performQuery($word);
    return;
  }

  # if content_length is zero and query string is empty
  if (($ENV{CONTENT_LENGTH}==0) &&
    (length($ENV{"QUERY_STRING"})==0)){
    # we're not decoding a form yet => send the form 
    &send_header("$title");
    &send_index();
    &send_trailer();
    return;
  }
  
  # else: request from the web
  %forms=&cgiparse();
  
  if($forms{"BUILDINDEX"}){
    &send_header("Site Index");
    print &buildIndex();
    &send_trailer();
    return;
  }
  
  &send_header("Query Result");
  print &performQuery($forms{KEYWORDS});
  print "<HR>";
  &send_index();
  &send_trailer();
  return;
}

sub performQuery {
  local($query) = @_;
  
  # remove non-word characters
  $query = &html2text($query);
  $query =~ tr/\(\)a-zA-Z0-9\-\xc0-\xff/ /cs;
  $rawquery=$query;
  $query =~ tr/a-zA-Z0-9\-\xc0-\xff/ /cs;
  # remove leading and trailing whitespace
  $query =~ s/^\s*(.*\S)\s*/$1/;
  $pquery = $query;
  $context = $forms{CONTEXT};
    
  if($context =~ m:\(([^)]*)\):) {
    $context=$1;
  }else{
    $context="";
  }
  
  $thesaurus = $forms{THESAURUS};
  $substring = $forms{SUBSTRING};
  
  $days = $forms{DAYS};
  if(length($days)>0){         $pquery.=" -D $days";  }
  if(length($thesaurus)>0){    $pquery.=" -T";  }
  if(length($substring)>0){    $pquery.=" -S";  }
  if(length($context)>0){      $pquery.=" @ $context";  }
  
  # PJ: maybe optimize: if($#keywords>=0){
  # compile the original query string into perl 
  # and store name in $match
  $sub = 0; # get rid of warning
  ($match, $err, $sub)=&compilequery($rawquery);

  sub downbynumber {$b <=> $a;}
  local(@results) = sort downbynumber &getindex($pquery);
  # problems?
  return join("<br>",@errStack) if(@errStack);
   
  local($text,$hits);
  foreach $w (@results){
    $text .= "<li>".htmlForFile(split(/\n/,$w));
	if (++$hits > $MAXHITS) {
       return "<ol><font size=2>$text</font></ol>
	   <P>More than $MAXHITS hits. Use a more restrictive search</P>";
    }
  }
  return "no matches found." unless ($text);
  return "<ol><font size=2>$text</font></ol>\n";
}

# configure this to build the HTML text entry for each file
sub htmlForFile {
  local($freq,$file,$title,$time,@hits)=split(/\n/,$w);
  local($url) = &translateback($file);
  if(length($title)==0){
    $title = $file;
    $title =~ s|.*/(.*/.*)$|$1|g;
  }
  local($result) = " <a href=\"$url\">$title</a> ";
  $result .= join("<br>","<i>$time</i>",@hits);
  $result;
}

# print the CGI script header
sub send_header {
    local($title)=@_;
    print "Content-type: text/html\n\n";
    $header =~ s/###TITLE###/$title/g;
    print $header;
}

sub send_trailer {
  print $footer;
}

# display the Forms interface 
## PJ sollte extern gesetzt werden koennen. Muss hier gesetzt
##    werden, falls das Form auch in den Results wieder gezeigt
##    werden soll, da dann der workaround mit externem form
##    nicht klappt.
sub send_index {
    local($scriptname) = $ENV{"SCRIPT_NAME"};
    print "Use the form below to specify your search, or consult the ";
    print "<a href=\"$scriptname?BUILDINDEX=y\">site index</a>";

    print "<FORM ACTION=\"$scriptname\">\n";
    print <<'END';

  Find words:
   <blockquote>
   <INPUT NAME="KEYWORDS"  SIZE=50><BR>
   Tips: You can use "and" (default) and "or" with search terms.<BR>
   Write umlauts like &Auml; as Ae, ...<P>

   Examples: <I><BR>
   <TT>- </TT>"cat and mouse" - or simply "cat mouse"<BR>
   <TT>- </TT> "(cat or mouse) and not dog" *<BR>
   <TT>- </TT> "cat or mouse"<BR>
   <TT>&nbsp; &nbsp;</TT>*) "not" operates only within the set of files containing<BR>
   <TT>&nbsp; &nbsp; &nbsp;</TT> at least one of "cat", "mouse" or "dog".<BR>
   </I><BR>
  <INPUT TYPE="submit" VALUE="Start Search">
  </blockquote>
  
  Specify options:
  <ul>
  <li><inPUT TYPE="checkbox" NAME="SUBSTRING"
   VALUE="substring" CHECKED> Use <B>Substring Matching</B> 
   to extend search
  <li><B>Don't Show</B> documents older than 
   <INPUT NAME="DAYS" VALUE="" SIZE=2>  days <BR>
  </UL> 
  </FORM>
END
}

# parse data from CGI request and store it as name/value pairs
sub cgiparse {
  if ($ENV{'REQUEST_METHOD'} eq "POST") {
    read(STDIN, $buffer, $ENV{'CONTENT_LENGTH'});
  } else {
    $buffer = $ENV{'QUERY_STRING'};
  }
  local(@query_strings) = split("&", $buffer);
  foreach $q (@query_strings) {
    $q =~ s/\+/ /g;
    ($attr, $val) = split("=", $q);
    $val =~ s/%/\n%/g;
    local($tmpval);
    foreach (split("\n",$val)){
      if(m:%(\w\w):){
        local($binval) = hex($1);
        if(($binval>0)&&($binval<256)){
          local($htmlval) = pack("C",$binval);
          s/%$1/$htmlval/;
        }
      }
      $tmpval .= $_;
    }
    $forms{$attr} = $tmpval;
  }
  %forms;
}

# parse query
sub parsequery{
  local($query)=@_;
  local($context,$thesaurus,$substr);
  # preprocess whitespace and discard spaces after @ and -D
  $query =~ tr/ \t/ /s;
  $query =~ s/@ /@/g;
  $query =~ s/-D /-D/g;
  $query =~ s/^-D/ -D/g;
  $_=$query;
  # "optional URL context as @-sign"
  if(m:^([^@]*)\s+@(.*)$:){
    $context=$2;
    $_=$1;
  }
  while(m:\s+-[SDT]\d*$:){
    # "turn on "global" thesaurus" by adding -T"
    if(m:^(.*)\s+-T$:){
      $thesaurus="y";
      # print "turn on thesaurus\n";
      $_=$1;
    } 
    # "turn on matching substrings by adding -S"
    if(m:^(.*)\s+-S$:){
      $substr="y";
      # print "turn on substring matching\n";
      $_=$1;
    }
    # "turn on modified since n days" by adding -D"
    if(m:^(.*)\s+-D(\d+)$:){
      $days=$2;
      # print "turn on modified since $days\n";
      $_=$1;
    }
  } 

  @list=split(/ /,$_);

  $expectword="y" unless($days && $#list==-1);
  foreach $w (@list){
    $_ = $w;
    tr/A-Z/a-z/;
    if(/^and$/) {
      if($expectword) {$err="$w"; last;}
      $expectword="y";
      $bool .= "&";
    }elsif(/^or$/){
      if($expectword) {$err="$w"; last;}
      $expectword="y";
      $bool .= "+";
    }else{
      unless($expectword) {
        $bool .= "&";
      }
      $expectword="";
      push(@querystring,$w); 
    }
  }
  if($expectword){
    push(@errStack,"syntax error in query: must end with searchword!");
    return;
  }
  if($err){
    push(@errStack,"syntax error in query near '$err'!");
    return;
  }
  
  return($context,$thesaurus,$substr,$bool,$days,@querystring);
}

# get index entries matching query
sub getindex{
  local($context,$thes,$substr,$bool,$days,@query) = &parsequery(@_);
  return if(@errStack);
  
  local(@list,$count,$item,$w,@wordnum,$grepexpr);
  local($limit,@allids,@keywords);
  if($days){
    $limit=time()-(60*60*24*$days) unless($days==0);
  }
  foreach $item (@query){
    ++$count;
    local($w);
    $_=$item;
    local($thesflag)=$thes;
    if (/{(.*)}/) {
      $_ = $1;
      $thesflag="y";
    }
    # convert e.g. "Picture" to "picture"
    if(/^[A-Z][^A-Z]*$/){
      tr/A-Z/a-z/;
    }
    # evaluate thesaurus
    if ($thesflag && length($thesfile)>0) {
      $wordnum{$_}=$count;
      local(@synonyms)=split(/\n/,&thesread($thesfile,$_));
      foreach $w (@synonyms){
        push (@keywords,$w);
        $wordnum{$w}=$count;
      }
    } 
    if(length($_)<$MINLEN) {
      print "ignored: $_ (too short)<br>\n";
      push(@ignored,$count);
    }else{
      $w=$_;
      push (@keywords,$w);
      $wordnum{$w}=$count;  
    }
  }

  $grepexpr = join("|",@keywords);
  # trick for speedup: if no keywords, set grepexpr to "^--"
  $grepexpr = "^--" unless(@keywords > 0);

  local($pat);
  open(FP,"$indexfile") || die "$!";
  while(<FP>){
    if(/^--/o){ # seperator
      last; # break loop
    }
    next unless (/$grepexpr/o);
    foreach $w (@keywords){
      $pat = $substr ? '\S*'.$w.'\S*' : $w;
      if(/^($pat)\s+(.*)$/){
        $word=$1;
        @files=split(/ /,$2);
        foreach (@files){
	  if(/(.*):(.*)/ || /(.)(.*)/){
            $freq=$1; $fileid=$2;
          }
	  if (length($word)>0) {
	    if (hex($fileid) != 0) {
              $token=$wordnum{$w};
              $entry=join("\n",$fileid,$token,$word,$freq);
	      push(@allids,$fileid);
              push(@list,$entry);
            } elsif ($word eq $w) {
              $token=$wordnum{$w};
              print "ignored: $word (stopword)<br>\n";
              push(@ignored,$token);
	    }
	  }
        }
      }
    }
  }
  # step 2: read path information
  $grepexpr = join("|",@allids);
  $grepexpr = '\S+' unless (@keywords>0); # match all if none given

  local($name,$fileid);
  while(<FP>){
    if(m:^(\S*/\S*):){  # was: "m:^(/.*):" CN 9/98 
      $dir = $1;
    }
    if(/^($grepexpr)\s+(.*) \/(\S+)\s+(.*)$/o){
      $fileid = $1;
      $name = "$dir/$2";
      $modTime = $3;
      $title = $4;
      # special case: no keywords -> get all files matching $limit
       if(@keywords == 0 && $modTime>=$limit){
         $entry = join("\n",$name,"","","",$title,$modTime);
         push(@list,$entry);
         next; # continue loop
      }
      # if file doesn't match $limit
      if($limit != 0 && $modTime<$limit){
        # remove it from list
        @list = grep(!/^$fileid\n/,@list);
      }
      # else replace fileid in @list with real path
      else{
        foreach(@list){
          if(/^$fileid\n/){
            s/^$fileid\n/$name\n/; # replace id with real path
            $_ .= "\n$title"; # append title
            $_ .= "\n$modTime"; # append mod. time
          }
        }
      }
    }
  }
  close(FP);

  if($context){
    # translate virtual<->physical path
    local($phys)=&translate($context);
    # remove those paths that don't match context
    @list=grep(/$phys/,@list);
  }

  ## we now have a @list of index entries containing
  ## only those matching one of the search terms

  if($#keywords>=0){
    # if keywords given evaluate expression
    @list=sort(@list);
    
    # PJ - change @list to collect word aggregates...
    # &evaluateexpr($bool,@list); #return
    # PJ - new query code: extract something usable
    #      from list and call the sub for our query
    #      - this code replaces evaluateexpr, all 
    #        lower subs and all refs to $bool
    # PJ - todo: improve efficiency by moving stuff into
    #      the environment (arg passing)
    @out=(); $w="";
    
    # loop over all entries in list
    foreach $i (0 .. $#list+1){ # sic!
      ($path,$token,$word,$freq,$title,$modTime)=
        split(/\n/,$list[$i]) if($i <= $#list);
      ($lastpath,$lasttitle,$lasttime)=($path,$title,modTime) if (0==$i);
      if(($lastpath ne $path) || ($i==$#list+1)) {
        $hits="";
        ($score,%hits)=&{$match}($w);
	foreach (sort keys %hits) {
           $hits.="$_: $hits{$_}\n";
	}
        push (@out,join("\n",$score,$lastpath,$lasttitle,&timetostr($lasttime),$hits)) if $score;
        $w="";
      } 
      $w.="$word:$freq,";
      $lastpath=$path;
      $lasttitle=$title;
      $lasttime=$modTime;
    }
    @list=@out; 
  }else{
    # else just reorder
    foreach $w (@list){
      ($path,$token,$word,$freq,$title,$time)=split(/\n/,$w);
      $w=join("\n","1",$path,$title,&timetostr($time));
    }

    @list;
  }
}

# make list of all tokens in indexfile
sub buildIndex {
 local($scriptname) = $ENV{"SCRIPT_NAME"};
  local(@list,$token,$rest,$prefix, $lastprefix,$result,$count);
  open(FP,"$indexfile") || die "$!";
  while(<FP>){
    if(/^--/o){ # seperator
      last; # break loop
    }
    ($token,@rest)=split(' ');
    next if $token =~ /[A-Z]/;
    ($prefix = $token) =~ s/^(.).*/$1/;
    $prefix =~ tr/a-z/A-Z/;
    $count = scalar(@rest);
	# Following Jan Kalin's proposal, exclude stop listed words:
	next if $count == 1 and $rest[0] eq "0";

    if($prefix ne $lastprefix){
      $result .= "<p><b><a name=\"$prefix\">$prefix</a></b><br>" ;
      push(@allPrefixes,$prefix);
    }
    $result .= "<a href=\"$scriptname?KEYWORDS=$token\">$token</a> $count<br>";
    $lastprefix = $prefix;
  }
  local($linkList);
  foreach(@allPrefixes){
    $linkList .= "<a href=\"#$_\">$_</a> ";
  }
  "$linkList<hr><font size=2>$result</font>";
}

# evaulate a thesaurus file for a given term
sub thesread {
  local($thesfile,$word)=@_;
  local($last,$result,$line)="";
  local($allowed)="EQ|AB|UF";
  unless (open(fpInput,$thesfile)) {
    push(@errStack,"Cannot open thesaurus file $thesfile\n");
    return undef;
  }
  while(<fpInput>){
    $line++;
    if (m:^(\S+)\s+$:) {
      $last=$1;
    }elsif((m:^\s+($allowed)\s+(\S+):)&&($last eq $word)) {
      $result .= "$2\n";
    }
  }
  close(fpInput);
  $result;
}

# translate URL to physical
sub translate {
  local($url)=@_;
  local($aliasdone);
  local($_)=$url;
  s|/+$||; # strip off a trailing "/"

  foreach $key (keys(%aliases)){
    if( /^$key/ ){
      s/^$key/$aliases{$key}/;
      $aliasdone="y";
      #print "replacing $key with $aliases{$key}\n";
    }
  }
  if(!$aliasdone && $docroot){
    $_ = $docroot.$_;
  }
  return $_;
}

# translate physical to URL
sub translateback {
  local($url)=@_;
  local($aliasdone);
  local($_)=$url;
  s/(.*)\/$/$1/; # strip off a trailing "/"

  foreach $key (keys(%aliases)){
    if(/^$aliases{$key}/){
      s/^$aliases{$key}/$key/;
      $aliasdone="y";
      # print "replacing $aliases{$key} with $key\n";
    }
  }
  if(!$aliasdone && $docroot){
    s/$docroot//;
  }
  return $_;
}

# convert time to string
sub timetostr{
  local($time)=@_;
  local(@timeEntries)=localtime($time);
  local($mday) = $timeEntries[3];
  local($mon) = $timeEntries[4];
  local($year) = $timeEntries[5] + 1900;
  local($wday) = $timeEntries[6];
  local($weekday)=(Sun,Mon,Tue,Wed,Thu,Fri,Sat)[$wday];
  local($month)=(Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec)[$mon];
  local($result)="$weekday $mday $month $year";
  $result;
}

# iso2html - translate iso 8 bit characters to HTML
#
# Thanks to
# Pierre Cormier (cormier.pierre@uqam.ca)
# Universite du Quebec Montreal
sub initTables {
  foreach (0..191) { $isohtml[$_] = pack("C",$_);}
  $isohtml[hex('c0')] = '&Agrave;';
  $isohtml[hex('c1')] = '&Aacute;';
  $isohtml[hex('c2')] = '&Acirc;';
  $isohtml[hex('c3')] = '&Atilde;';
  $isohtml[hex('c4')] = '&Auml;';
  $isohtml[hex('c5')] = '&Aring;';
  $isohtml[hex('c6')] = '&AElig;';
  $isohtml[hex('c7')] = '&Ccedil;';
  $isohtml[hex('c8')] = '&Egrave;';
  $isohtml[hex('c9')] = '&Eacute;';
  $isohtml[hex('ca')] = '&Ecirc;';
  $isohtml[hex('cb')] = '&Euml;';
  $isohtml[hex('cc')] = '&Igrave;';
  $isohtml[hex('cd')] = '&Iacute;';
  $isohtml[hex('ce')] = '&Icirc;';
  $isohtml[hex('cf')] = '&Iuml;';
  $isohtml[hex('d0')] = '&ETH;';
  $isohtml[hex('d1')] = '&Ntilde;';
  $isohtml[hex('d2')] = '&Ograve;';
  $isohtml[hex('d3')] = '&Oacute;';
  $isohtml[hex('d4')] = '&Ocirc;';
  $isohtml[hex('d5')] = '&Otilde;';
  $isohtml[hex('d6')] = '&Ouml;';
  $isohtml[hex('d7')] = '&times;';
  $isohtml[hex('d8')] = '&Oslash;';
  $isohtml[hex('d9')] = '&Ugrave;';
  $isohtml[hex('da')] = '&Uacute;';
  $isohtml[hex('db')] = '&Ucirc;';
  $isohtml[hex('dc')] = '&Uuml;';
  $isohtml[hex('dd')] = '&Yacute;';
  $isohtml[hex('de')] = '&THORN;';
  $isohtml[hex('df')] = '&szlig;';
  $isohtml[hex('e0')] = '&agrave;';
  $isohtml[hex('e1')] = '&aacute;';
  $isohtml[hex('e2')] = '&acirc;';
  $isohtml[hex('e3')] = '&atilde;';
  $isohtml[hex('e4')] = '&auml;';
  $isohtml[hex('e5')] = '&aring;';
  $isohtml[hex('e6')] = '&aelig;';
  $isohtml[hex('e7')] = '&ccedil;';
  $isohtml[hex('e8')] = '&egrave;';
  $isohtml[hex('e9')] = '&eacute;';
  $isohtml[hex('ea')] = '&ecirc;';
  $isohtml[hex('eb')] = '&euml;';
  $isohtml[hex('ec')] = '&igrave;';
  $isohtml[hex('ed')] = '&iacute;';
  $isohtml[hex('ee')] = '&icirc;';
  $isohtml[hex('ef')] = '&iuml;';
  $isohtml[hex('f0')] = '&eth;';
  $isohtml[hex('f1')] = '&ntilde;';
  $isohtml[hex('f2')] = '&ograve;';
  $isohtml[hex('f3')] = '&oacute;';
  $isohtml[hex('f4')] = '&ocirc;';
  $isohtml[hex('f5')] = '&otilde;';
  $isohtml[hex('f6')] = '&ouml;';
  $isohtml[hex('f7')] = '&DIVIS;';
  $isohtml[hex('f8')] = '&oslash;';
  $isohtml[hex('f9')] = '&ugrave;';
  $isohtml[hex('fa')] = '&uacute;';
  $isohtml[hex('fb')] = '&ucirc;';
  $isohtml[hex('fc')] = '&uuml;';
  $isohtml[hex('fd')] = '&yacute;';
  $isohtml[hex('fe')] = '&thorn;';
  $isohtml[hex('ff')] = '&yuml;';

  # preset iso2text variable settings
  foreach (0..191) { $iso2text[$_] = pack("C",$_);}
  foreach (hex('c0')..hex('ff')) {
    $iso2text[$_] = substr($isohtml[$_],1,1);
  }
  # now assign exceptions:
  $iso2text[hex('c4')] = 'Ae';
  $iso2text[hex('c6')] = 'AE';
  $iso2text[hex('d0')] = 'ETH'; # ???
  $iso2text[hex('d6')] = 'Oe';
  $iso2text[hex('d7')] = 'x';
  $iso2text[hex('dc')] = 'Ue';
  $iso2text[hex('de')] = 'Th'; # thorn ???
  $iso2text[hex('df')] = 'sz';
  $iso2text[hex('e4')] = 'ae';
  $iso2text[hex('e6')] = 'ae';
  $iso2text[hex('f7')] = 'D';  # Divis?
  $iso2text[hex('fc')] = 'ue';
  $iso2text[hex('fe')] = 'th'; #  thorn

  # set html2iso variable
  foreach (1..255) {
    $html2iso{$isohtml[$_]}=pack("C",$_);;
  }
}

sub iso2html {
  local($input)=@_;
  unless(defined($isohtml[0])){
    &initTables;
  }
  local(@car) = split(//,$input);
  local($output);
  foreach (@car) {
    $output .= $isohtml[ord($_)];
  }
  $output;
}

sub iso2text {
  local($input)=@_;
  unless(defined($isohtml[0])){
    &initTables;
  }
  local(@car) = split(//,$input);
  local($output);
  foreach (@car) {
    $output .= $iso2text[ord($_)];
  }
  $output;
}

sub html2iso {
  local($input)=@_;
  unless(defined($isohtml[0])){
    &initTables;
  }
  local(@car) = split(/;/,$input);
  local($output);
  foreach (@car) {
    if(/(.*)&(.*)/){
      $output .= $1;
      $output .= $html2iso{"&$2;"};
    }else{
      $output .= $_;
    }
  }
  $output;
}

sub html2text {
  return &iso2text(&html2iso(@_));
}

sub encodehtml{
   # encode special chars into html entities
   # note that &#xFF; seems legal now, too
   local($_)=@_;
   #   <   >   &   "   '
   s/([\x3c\x3e\x26\x22\x27])/"&#".ord($1).";"/geo;
   $_
}

sub encodeurl {
   # encode non-standard chars for urls
   local($_)=@_;
   s/([^_\+\-A-Za-z0-9\/\.])/"%".unpack(H2,$1)/geo;
   $_
}

sub compilequery {
   # given a boolean query string, return a corresponding sub
   # in:   querystring
   # env:  substring
   # out:  name, errors, subroutine string (not required)
   # side: subroutine returning (score,@matchstrings)
   # Bug: - if using op="or", "not" only makes sense as "and not" or worse
   #        as "(...) and not". For the time being, better assume implicit "and".
   local($_)=@_;
   ### CN 5/98 bug fix: ${$} will be negative on (some?) windows systems
   ### my($name)="query${$}_".time; # generated name of sub
   my($name)="query_".time; # generated name of sub
   my($need_op,$op,$no_score)=(0,"and",0); # default operator for "word word"
   my($tmp,$scoring)=("","");
   my($sub)="";
   s/\n/ /go;

   # translate query to perl; use $op for missing, but required operator
   loop: while($_) {
      # whitespace
      s@^\s+@@o			and $sub.=" "    		and next loop;
      # parantheses
      s@^(\()@@o		and do {
         $sub.=" $op " if $need_op; $need_op=0;
	 $sub.=" $1 ";
      }								and next loop;
      s@^(\))@@o		and do {
         $need_op=1;
	 $sub.=" $1 ";
      }								and next loop;
      # and/2, or/2 (infix)
      s@^(and|or)\b@@o  	and do {
         # careful with score computing - or short circuits
         $sub.=" $1 "; $need_op=0;
	 1
      }								and next loop; 
      # not/1 (prefix)
      s@^(not)\b@@o  		and do {
         $sub.=" $op " if $need_op; $need_op=0;
	 $sub.=" not ";
      } 							and next loop; 
      # good word
      s@^([a-z0-9]+)\b@@oi 	and do {
         $sub.=" $op " if $need_op;
	 $tmp=""; $tmp ='\b' if not $substring; $tmp.=$1; $tmp.='\b' if not $substring;
	 $scoring.="while(/(\\w*$tmp\\w*):(\\d+)/iog){\$fw{\$1}+=\$2};\n   "; # for now simply add everything
	 $sub.="/$tmp/io ";
	 $need_op=1;
      } 							and next loop;
      # someone hacking us???
      s@^(.*?)\s+@@o            and do {
         push (@errstack, "ice2: Questionable query substring: $1 - skipping\n")
      } 							and next loop;
      push (@errstack, "ice2: This cannot happen: query remnant: $_\n");
      $_="";      
   }
   
   # we could/should return a reference to a sub, but for ease
   # of downgrading to perl4, we use the oldfashioned eval way
   $sub.=") and do {foreach(sort keys \%fw){\$rc+=\$fw{\$_}}};\n   return(\$rc,\%fw)\n}\n";
   $sub ="sub $name {\n   local(\$_)=\@_;\n   my(\$rc)=0;\n   my(\%fw)=();\n   # compute total score\n   $scoring# compute expression\n   (0, $sub;\n   "; #
   eval ($sub) if $name;
   # everything ok?
   if ($@ or not $name) {
      $name=""; # $name="compilequery_failed";
      push(@errStack, "ice2: Query compilation failed\n");
   }
   return($name,$@,$sub);
}

sub compilequery_failed {
   print main::STDERR "ice2: Query compilation failed\n";
}
