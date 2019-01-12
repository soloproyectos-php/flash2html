# Flash2html
Transform HTML for Flash to standard HTML.

Adobe Flash (AS3) supports a subset of html tags that can be used to enrich texts. But most of those tags do not meet the HTML5 standard. For example, the `<FONT />` tag is not a valid HTML5 tag.

This class allows us to migrate invalid tags to valid HTML5 tags.

## Example
```PHP
// prioprietary HTML
$text = '<P ALIGN="LEFT"><FONT FACE="Verdana" SIZE="12" COLOR="#000000" LETTERSPACING="0" KERNING="0"><B>Integer sed lacus libero</B>, tempus sodales nisi. In hac habitasse platea dictumst. Pellentesque nec odio est, a iaculis lacus. Maecenas ante ligula, pellentesque in mattis sed, scelerisque nec dolor. Duis lorem enim, pretium vel luctus ut, <I>varius ut nunc</I>.</FONT></P><P ALIGN="LEFT"><FONT FACE="Verdana" SIZE="12" COLOR="#000000" LETTERSPACING="0" KERNING="0"></FONT></P><LI><FONT FACE="Verdana" SIZE="12" COLOR="#000000" LETTERSPACING="0" KERNING="0"><A HREF="http://www.php.net" TARGET=""><U>Vestibulum</U></A> sed risus et nunc consequat faucibus.</FONT></LI><LI><FONT FACE="Verdana" SIZE="12" COLOR="#000000" LETTERSPACING="0" KERNING="0">Sed quam metus, consequat nec dignissim quis, mattis ac quam.</FONT></LI><LI><FONT FACE="Verdana" SIZE="12" COLOR="#000000" LETTERSPACING="0" KERNING="0"><A HREF="mailto:qkehkrqnqweh@wkjehkrw.com" TARGET=""><U>Maecenas</U></A> venenatis volutpat purus, vitae sagittis enim cursus eget.</FONT></LI><LI><FONT FACE="Verdana" SIZE="12" COLOR="#000000" LETTERSPACING="0" KERNING="0">Proin elementum hendrerit nibh sit amet elementum.</FONT></LI><P ALIGN="LEFT"><FONT FACE="Verdana" SIZE="12" COLOR="#000000" LETTERSPACING="0" KERNING="0"></FONT></P><P ALIGN="LEFT"><FONT FACE="Verdana" SIZE="12" COLOR="#000000" LETTERSPACING="0" KERNING="0"><U>Nullam tempus molestie sem</U>, non venenatis ligula suscipit a. Suspendisse dignissim, nulla quis euismod iaculis, libero lectus aliquet dui, sit amet varius ante nulla eget eros. Nulla facilisi. Cras et erat dui.</FONT></P>'; 

// prints standard HTML
$f = new Flash2Html();
echo $f->html($text);
```
