%pragma lexer.unicode 1

%skip   space              [\x20\x09\x0a\x0d]+
%token  doc_               /\*\*                      -> docblock

%skip   docblock:space     [\x20\x09\x0a\x0d]+
%skip   docblock:star      \*(?!/)
%token  docblock:_doc      \*/                         -> default
%token  docblock:at        @(?!\s)                     -> annot
%token  docblock:text      [^*@]+|@(?=\s)|\*(?!/)

%token  annot:valued_identifier \\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*(?=\()
%token  annot:simple_identifier \\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)* -> __shift__
%token  annot:parenthesis_  \(                          -> value

%skip   value:star          [*](?!/)
%skip   value:space         [\x20\x09\x0a\x0d]+
%token  value:_parenthesis  \)                          -> __shift__ * 2
%token  value:at            @(?!\s)                     -> annot
%token  value:comma         ,
%token  value:brace_        {
%token  value:_brace        }
%token  value:double_colon  ::
%token  value:colon         :
%token  value:equals        =
%token  value:quote_        "                           -> string
%token  value:null          \b(?:null|NULL)\b
%token  value:boolean       \b(?:true|TRUE|false|FALSE)\b
%token  value:float         -?(0|[1-9]\d*)(?=[eE\.])(\.\d+)?([eE][+-]?\d+)?
%token  value:integer       -?(0|[1-9]\d*)
%token  value:identifier_ns \\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)+
%token  value:identifier    [a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*

%token  string:string      (?:[^"\\]+|(\\\\)*\\"|(\\\\)+|\\?[^"\\]+)+
%token  string:_quote      "                           -> __shift__

#annotations:
    ::doc_::
    (::text:: | annotation())*
    ::_doc::

#annotation:
    ::at::
    (
        <simple_identifier>
        | ( <valued_identifier> ::parenthesis_:: ( parameters() )? ::_parenthesis:: )
    )

#list:
    ::brace_:: ( (value() ( ::comma:: value() )*) ::comma::? )? ::_brace::

#map:
    ::brace_:: pairs() ::comma::? ::_brace::

pairs:
    (pair_equal() | pair_colon()) ( ::comma:: (pair_equal() | pair_colon()) )*

#pair_equal:
    (<identifier> | <identifier_ns> | string() | <integer> | <float> | constant()) ::equals:: value()

#pair_colon:
    (<identifier> | <identifier_ns> | string() | <integer> | <float> | constant()) ::colon:: value()

#value:
    <null> | <boolean> | string() | <integer> | <float> | map() | list() | annotation() | constant()

#parameters:
    ( parameter() ( ::comma:: parameter())* ::comma::? )?

parameter:
    named_parameter() | unnamed_parameter()

#named_parameter:
    <identifier> ::equals:: value()

#unnamed_parameter:
    value()

#constant:
    reference() ::double_colon:: <identifier>

#string:
    ::quote_:: <string>? ::_quote::

#reference:
    <identifier> | <identifier_ns>
