# DOCX to JATS XML Converter Plugin 
The plugin for OJS 3.1+ that allows to convert articles in DOCX to JATS XML format. The output is compatible with Texture plugin - JATS XML online editor.

DOCX is an archive that complies with Open Office XML format. It is commonly used for creation and editing of text documents, including as an exchange format for scientific articles, mainly between an author and a publisher. The converter is designed to support the output from MS Word, LibreOffice Writer, and Google Docs.    

This plugin is aimed at helping publishers that are using JATS XML as a pivotal format for their publication workflow. The idea behind the converter is to create a basic structure of the document from given DOCX file and then prepare the manuscript for the production by JATS XML online editor, like the Texture plugin. The converter is written in pure PHP and doesn't require any additional external extensions for basic functionality.

## Installation
The plugin can be installed in two ways, 1) by downloading the latest stable release or 2) by cloning the master branch. The latter can be accomplished with Git by cloning the repo with submodules into `plugins/generic` directory starting from the web root of OJS instance. It's as simple as: `git clone --recurse-submodules https://github.com/Vitaliy-1/docxConverter.git`.

After installation plugin should be activated in the plugins menu: `Settings -> Website -> Plugins -> check enable near DOCX to JATS XML Converter Plugin`. The button `Convert to JATS XML` will appear as a dropdown under Draft DOCX file on any Workflow stage.

_Important note:_ as parsing references is not yet supported, the only way to add them is manually with available JATS XML WYSIWYG editor - [Texture Plugin](https://github.com/pkp/texture) for OJS 3.1+. How to use them together: ![](https://e-medjournal.com/suppl/convert.gif)

## What article elements are supported? 
It is planned that DOCX to JATS XML Converter will support all major features of DOCX. The table below lists elements that are already supported and are planned to be developed in near future. The row `Planned for the 1.0.0 release` means that it's likely to be included in the first stable release, otherwise it's planned to be included later.

| Feature                     | Supported        | Planned for the 1.2.0 release | Notes |
|-----------------------------|------------------|-------------------------------|-------|
|Paragraphs                   |:heavy_check_mark:|                               |All formatted text in the paragraph can be neste, e.g. **bold, _bold + italic_**. |
|**Bold**                     |:heavy_check_mark:|                               |       |
|*Italic*                     |:heavy_check_mark:|                               |       |
|Text <sup>superscript</sup>  |:heavy_check_mark:|                               |       |
|Text <sub>Subscript</sub>    |:heavy_check_mark:|                               |       |
|~~Strikethrough~~            |:heavy_check_mark:|                               |       |
|Lists                        |:heavy_check_mark:|                               |Can be nested |
|List style                   |:heavy_check_mark:|                               |       |
|Headings and sections        |:heavy_check_mark:|                               |Can be nested; OOXML headings are tranformed to the JATS XML sections with title and correspondent level |
|Tables                       |:heavy_check_mark:|                               |       |
|Cells with row- and colspan  |:heavy_check_mark:|                               |       |
|Table caption                |:heavy_check_mark:|                               |       |
|JPEG and PNG Figures         |:heavy_check_mark:|                               |       |
|Figure caption               |:heavy_check_mark:|                               |       |
|Diagrams                     |                  |                               |       |
|Formulas                     |                  |:heavy_check_mark:             |       |
|Footnotes                    |                  |:heavy_check_mark:             |       |
|MS Word citations            |                  |                               |       |
|Raw citations                |:heavy_check_mark:|                               |       |
|Zotero citations             |:heavy_check_mark:|                               |Zotero/Mendeley Plugin for MS Word, Mendeley plugin for LibreOffice Writer (export with MS Word support button)|
|External links               |:heavy_check_mark:|                               |       |
|OOXML metadata               |                  |                               |OOXML contains limited set of metadata and this feature is rarely used by authors|
|Article's metadata from OJS  |:white_check_mark:|                               |Metadata, like authors names, their affiliation, and article title is transfered from OJS; doesn't support abstracts yet|

## Zotero support
The plugin supports Zotero plugin for MS Word. To add structured references install Zotero plugin according to the [instructions](https://www.zotero.org/support/word_processor_plugin_troubleshooting). Please note that Zotero plugin for Libreoffice Writer and Google Docs isn't supported as they don't export bibliography in DOCX(OOXML) format. 

## How to achieve best results?
The best results can be obtained only with articles that are structured. DOCX to JATS Converter Plugin should work with DOCX files produced by Google Docs, MS Word, and LibreOffice Writer. Although, there can be some drawbacks because these formats are not fully intercompatible. DOCX is an archive that contains files that represent document's structure, inserted files and styling in [OOXML format](http://officeopenxml.com/).

### General recommendations

#### Article body

##### Paragraphs
Paragraphs are text chunks separated by line breaks to which different styles can be applied. To be correctly recognized as article paragraphs, the text should have a default style or normal text style depending on the naming conventions used by the text editor.

##### Sections and section titles 
The inner structure of OOXML doesn't recognize article's section as a separate entity. To be recognized correctly section should be delimited by a heading. The Heading in OOXML is a paragraph to which correspondent style is applied - `heading` or `title` depending on the text editor.

##### Lists
According to the OOXML, lists are enumerate entities which consist from one to several items. Each item can have own style similarly to paragraphs, such as `normal text`, `heading`, etc. Enumerate property has higher priority over all styles except `bibliography`, this means that if list has heading property the latter will be ignored by the converter and entity will be recognized only as a list. Nested lists are supported. 

##### Tables
Tables are recognized as separate elements with unique encoding in OOXML. In terms of a structure they are much similar to JATS XML - consist of rows and cells which may span on several rows or columns. Unfortunately table title or caption isn't linked to the table in text editors, this means they are usually encoded as simple paragraphs and cannot be recognized correctly with the plugin. The content of the cell isn't distinguishable from the paragraph; for the sake of simplicity if cell's content has only one paragraph, in the result JATS XML the paragraph tag will be dropped and text content directly appended to the cell. 

##### Formatted text
According to the OOXML, text is represented as runs with specific properties, such as bold, italic, superscript, subscript, underlined and strikethrough all of which are supported by the converter as well as by text editors which allow export in DOCX format. Text runs also contain information about font and it's size - they are not transferred to JATS XML as this format is designed to represent article's structure rather than styling. Text runs inside paragraphs, lists and table cells are handled in the same way by the converter.

##### Figures  
Most of text editors allow to insert images in the text in different formats, starting from JPEG/PNG and ending by data charts imported from other more specialized applications, e.g., LibreOffice Calc of MS Excel. The plugin supports only JPEG and PNG formats and cannot automatically provide conversion from charts to the supported image formats. The step of exporting chart as an image/picture should be done manually before inserting it to the text by the editor.      

#### Metadata

The ability of DOCX/OOXML format to provide metadata to the document is limited by its design and not fully reflect the metadata suitable for scientific article. It's common when using DOCX for writing/editing an article to include metadata, e.g., title, authors and their affiliations, funding, authors contribution, as a simple text at the start or the end of the document. This makes it impossible to parse metadata relying only on document's structure, this means that the plugin will recognize those elements as one of the element of article's body described above. 

The plugin is able to retrieve metadata from OJS, in particular from the submission, to which DOCX file is attached. Metadata is written inside JATS XML `front` tag automatically.

#### References

Bibliography according to OOXML specifications in most simple way is defined as a paragraph to which `Bibliography` style is applied; the style name may be different depending on an application or language pack. All content of such paragraphs is parsed and saved as a mixed citation in the article's back part. Currently, the plugin isn't able to recognize structural elements of the elements, e.g., title, year of the publication, authors' family and given name, etc.; it's saved as unstructurized text chunk. 

There are more advanced tools that allow to import/save references preserving their structure. DOCX Converter support [Zotero plugin for MS Word](https://www.zotero.org/support/word_processor_plugin_usage), which allows to include in the document citations found on the web.       

### Google Docs 
The link to the general example: [google document](https://docs.google.com/document/d/1O3m27j1UgQ6YXPZCBZ9pR-j5xHQ6byOuo7-WngfY-p8/edit?usp=sharing).
When working with Google Docs it should be kept in mind that it doesn't support citations, reference list, figure and table caption. General recommendations:
* _Sections and Headings._ To distinguish sections of scientific articles built-in headings can be used. Headings level represents the level of the section, thus they can be nested. Start a new line, choose a text style from a dropdown menu in the left upper corner: heading 1, heading 2 or another level. By default the text style is normal text. Guideline on Youtube: https://www.youtube.com/watch?v=q58KRXwg93E. Note: there is no need to create table of content.
* _Formatted text._ Bold, italic, and other text formatting is fully supported. The correspondent menu items are situated at the center position of the toolbar.
* _Tables and figures._ Tables and figures can be attached using `insert` button on the left-top side of the toolbar. Cells merging is supported. Unfortunately, caption are not supported by the Google Docs. How to create a table: https://www.youtube.com/watch?v=5HkarJaViQU; how to insert an image: https://www.youtube.com/watch?v=5Eh5WmTJ6qo
* _Lists._ Lists can be inserted using the items on a toolbar that are positioned to the right from formatted text options. Nested lists are supported, to change the level of the list item press `tab` (level down) or `shift + tab` (level up) keyboard buttons while the cursor is on the needed list item. How to manage lists: https://www.youtube.com/watch?v=g2UhdpozSdQ
* _Export as DOCX._ `File -> Download as -> Microsoft Word (.docx)`
* _Upload to OJS._ Produced file can be download to the Copyediting or Production stage as a Draft File, `Convert to JATS XML` button will appear in the dropdown menu under the file.
### LibreOffice Writer & Microsoft Word
The interface of the document editing window of LibreOffice Writer and MS Word are similar to described above. Styles that are applied to the paragraphs also can be found on the toolbar, as well as options for the text formatting, inserting lists and images. Bibliography style by default isn't available in the correspondent dropdown menu, instead it's listed in the advanced styles settings. If own style is created, the plugin will treat it as a style from which it was inherited.

To save a document in DOCX format in LibreOffice Writer: `File -> Save as -> Microsoft Word 2007-2013 XML (.docx)`. For MS Word DOCX is a default format. 

## Troubleshooting
For proposals and bugs tracking please open an issue on the [converter's page](https://github.com/Vitaliy-1/docxToJats/issues). 
