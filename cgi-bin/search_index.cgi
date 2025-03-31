#!/usr/local/bin/perl
# search_index.cgi - create index file
# 
# ICE Version 1.5 beta 3
# May 1998
# (C) Christian Neuss (ice@isa.informatik.th-darmstadt.de)
# Modified by Chris Samaritoni
# Jan 1999

 # To index your site simply execute this script as a CGI script.
 # For example http://www.yourname.com/cgi-bin/search_index.cgi
 # Re-index whenever changes are made to your documents.

$| = 1;
print "Content-type: text/html\n\n";
print "<HTML><HEAD><TITLE>Indexing Site</TITLE></HEAD>\n";
print "<BODY>\n";
print "<H1>Indexing site...</H1>\n<HR>\n<PRE>";

 #--- start of configuration --- put your changes here ---
 # NOTE: $ENV{'DOCUMENT_ROOT'} contains the full path to your web documents.

 # The physical directory/directories to scan for html-files.
 # Example:
 #  @SEARCHDIRS=("$ENV{'DOCUMENT_ROOT'}/docs","$ENV{'DOCUMENT_ROOT'}/articles"); 
@SEARCHDIRS=("$ENV{'DOCUMENT_ROOT'}");

 # The physical directory/directories to NOT scan for html-files. Maybe you have
 # a directory inside the SEARCHDIRS that you don't want to be search, such as 
 # your cgi-bin, postcards or wwwboard.
 # Example:
 #  @NOTSEARCHDIRS=("$ENV{'DOCUMENT_ROOT'}/postcard","$ENV{'DOCUMENT_ROOT'}/wwwboard");
@NOTSEARCHDIRS=("$ENV{'DOCUMENT_ROOT'}/cgi-bin", "$ENV{'DOCUMENT_ROOT'}/postcard");

 # Location of the index file. This file contains the list of indexed words.
 # Example:
 #  $INDEXFILE="$ENV{'DOCUMENT_ROOT'}/cgi-bin/index.idx";
$INDEXFILE="$ENV{'DOCUMENT_ROOT'}/cgi-bin/index.idx";

 # The ICE indexer will support full international characters by
 # converting them to a canonical form if $ISO is set to "y". For
 # servers that contain english text only, you can improve indexing
 # speed by setting $ISO to "n".
$ISO="n";

 # Type of system (for figuring out the path delimiting character)
 # that ice-idx.pl runs on. Select one of "UNIX", "MAC", or "PC"
 # Important: If you use NT, depending on the Perl binary, the
 # correct setting can be eith PC of UNIX!
$TYPE="UNIX";

 # Minimum length of word to be indexed
$MINLEN=3;

 # Stop indexing a word that appears in over X percent of all files
$MAXPERCENT=60;

# File suffixes to index (regular expression)
$SUFFIXES='\.(rtf|[sp]?html?|txt)$';
#--- end of configuration --- don't change anything below ---

require "find.pl";
local(@allfiles,@tempfiles,%freqlist,$file,%temp,$tempfile);

open(INDEX,">$INDEXFILE") || &error("Cannot open $INDEXFILE: $!\n");
&find (@SEARCHDIRS);

$count=0;

@tempfiles = @allfiles;
@allfiles = ();

%temp = map{$_, 1} @NOTSEARCHDIRS;

OUT: foreach $file (@tempfiles) {
    $tempfile = $file;
    while($tempfile =~ s#/[^/]+$##) {
         next OUT if ($temp{$tempfile});
    }
    push @allfiles, $file;
}

foreach $name (@allfiles){
print "indexing [$name]\n";
  $lastpercent=$percent;
  $percent=int(100*$count/@allfiles);
  if($percent>$lastpercent){ print $percent,"% ";}
  &indexfile($name);
  $count++;
  # every 100th file until the 1000th...
  if((($count % 100) == 0) && ($count >= 200) && ($count < 1200)){
    # remove the most frequent words so far from the index
    &removefrequent;
  }
}
&removefrequent;

# print sorted list of words and their fileids
foreach $w (sort keys(%index)){
  print INDEX "$w ",$index{$w},"\n";
}

print INDEX "--\n";

# print list of all files and their fileid
local($dir,$prevdir,$name);
foreach $w (sort keys(%files)){
  if($files{$w} =~ m:(.*)/([^/]*)$:){
    $prevdir = $dir;
    $name = $2;
    $dir = $1;
    if($prevdir ne $dir){
      print INDEX "$dir\n";
    }
    $title = $titles{$w};
    $mtime = $mtimes{$w};
    print INDEX "$w $name /$mtime $title\n";
  }
}

print "Done!\n</PRE><HR></BODY></HTML>\n";

### system("ps -vx | egrep 'perl|MEM'");

sub wanted {
  if($name=~/$SUFFIXES/i){ # file name ends
    push(@allfiles,$name);
  }
}

# modifies %files
sub removefrequent{
  local($num,$tmp);
  $numfiles = keys(%files);
  foreach $w (keys(%index)){
    ($tmp = $index{$w}) =~ s/[^ ]//g;
    $num = length($tmp);
    # don't index words in more then X % of the files
    if($num*100 > $MAXPERCENT*$numfiles){
      print "removing common word: $w [$num of $numfiles]\n";
      $index{$w}="0";
    }
  }
}

sub indexfile{
  local($file)=@_;
  local($title,$intitle,$freq);
# PJ - no directories
  return if -d $file;
  unless (-r $file && open(fpInput,"$file")){ # file readable?
    print "cannot read file [$file]\n"; ### XXX no printo
    return;
  }

$fileno++;
$fileid = sprintf ("%X ",$fileno);
$files{$fileid}=$file;

  local($dev,$ino,$mode,$nlink,$uid,$gid,$rdev,$size,
        $atime,$mtime,@dontcare);
  ($dev,$ino,$mode,$nlink,$uid,$gid,$rdev,$size,
        $atime,$mtime,@dontcare) = stat($file);
  # strip html tags?
  local($ishtml)=0;
  local($ishtmlregexp)='\.([sp]?html?)$';
  $ishtml=1 if $file=~m!$ishtmlregexp!i;
  # set input separator to the tag close character ">"
  $/ = ">";
  while(<fpInput>){
    s/&nbsp\;/ /ig;
    s/\s+/ /g;          # fold whitespaces into a single blank
    s/([^\n])</$1\n</g; # insert a CR before every '<'..
    s/>([^\n])/>\n$1/g; # .. and after every '>'

    foreach (split(/\n/,$_)){
      # opening title tag
      if(m:<title>:i){
        $intitle="y";
        $title="";
      }
      # closing title tag
      if(m:</title>:i){
        $intitle="";
      }

      # strip spurious tag delimeters
      s![<>]! !go if (!($ishtml));

      # outside a tag or inside META tag => index word
## PJ: BUG - we also want to index non-html 
##           so do some guessing to enable this
##           (above: try first lines to extract title from ascii)
##           (prefer subject: if exists)
      if(!/</ || /<meta /i ) {
        if(/<meta name="\S+"\s+content="([^"]+)">/i ) {
		  $_ = $1;
		  ## print "FOUND META TAG $_\n";
		}
        if( $ISO eq "y" && /[&\xc0-\xff]/){
          # convert html special chars and iso 8bit to text
          $_ = &html2text($_);
        }

        # if inside title
        if ($intitle){
          tr/a-zA-Z\xc0-\xff0-9\-/ /cs;
          $title.="$_";
        } else {
	  # the following line defines what you consider a "printable" char
          tr/a-zA-Z\xc0-\xff/ /cs;
          foreach (split(/ /,$_)){
            next unless (length($_)>=$MINLEN); # if too short skip
            if (/\;$/) {
              # get rid of trailing ";" that aren't part of &Xuml;
              s/((\w|\&[a-z,A-Z]+\;)+)\;?/$1/;
            }
            if(/^[A-Z][^A-Z]*$/){  # "Someword" to "someword"
              tr/A-Z/a-z/;
            }
            ###print "3. [$_]\n";
            $freqlist{$_}++;
            if(/[A-Z]/) { # store abbr. as all-lower, too
              tr/A-Z/a-z/;
              $freqlist{$_}++;
            }
          }
        }
      }
    }
  }
  $file =~ tr/\n/ /s;
  
  # convert MAC and PC path seperators to UNIX style slashes
  if($TYPE eq "MAC"){ $file =~ s|:|/|g;  }
  if($TYPE eq "PC") { $file =~ s|\\|/|g;  }
  
  # on a MAC, add the leading slash 
  if ($file =~ m/^[^\/]/) { $file = "/$file"; }
  
  $title =~ tr/\n/ /s;
  ### print INDEX "\@f $file\n";
  ### print INDEX "\@t $title\n";
  ### print INDEX "\@m $mtime\n";
  foreach $w (sort keys(%freqlist)){
    ###print INDEX "$freqlist{$w} $w\n";
    if($index{$w} ne "0"){
      $freq = $freqlist{$w};
      $freq .= ":" unless length($freq)==1;
      $index{$w} .= $freq.$fileid;
    }
    ### print "4. $freqlist{$w} $w\n";
  }
  $titles{$fileid}=$title;
  $mtimes{$fileid}=$mtime;

  undef %freqlist;
  close(fpInput);
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

sub error {
  print $_[0];
  print "</PRE></BODY></HTML>\n";
  exit;
}
