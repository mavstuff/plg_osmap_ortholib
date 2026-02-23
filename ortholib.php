<?php

use Alledia\OSMap\Plugin\Base;
use Alledia\OSMap\Sitemap\Collector;
use Alledia\OSMap\Sitemap\Item;
use Joomla\Registry\Registry;

class PlgOsmapOrtholib extends Base
{
    public function getComponentElement()
    {
        return 'com_ortholib';
    }

    /**
     * @param Collector $collector
     * @param Item      $menuparent
     * @param Registry  $params
     *
     * @return void
     */
    public function getTree(Collector $collector, Item $menuparent, Registry $params)
    {
        $searchPath = JPATH_SITE.DIRECTORY_SEPARATOR."media/com_ortholib/epubunzip";
        
        $directories = glob($searchPath . '/*' , GLOB_ONLYDIR);

        // Iterate by books. Each directory is a book
        foreach ($directories as $directory)
        {
            $containerfile = $directory."/META-INF/container.xml";
            if (file_exists($containerfile))
            {
                $containerxml = simplexml_load_file($containerfile);
                if (!empty($containerxml->rootfiles->rootfile["full-path"]))
                {
                    $content_opf_file_part = $containerxml->rootfiles->rootfile["full-path"];
                    $content_opf_file_full = $directory."/".$content_opf_file_part;

                    $bookid = basename($directory);

                    if (file_exists($content_opf_file_full))
                    {
                        $oebpsdir = dirname($content_opf_file_full);
                        $contentopfxml = simplexml_load_file($content_opf_file_full);
                        
                        $metadataxml = $contentopfxml->metadata->children("dc", TRUE);
                        if ($metadataxml)
                        {
                            $title = !empty($metadataxml->title) ? (string)$metadataxml->title : "";
                            $creator = !empty($metadataxml->creator) ? (string)$metadataxml->creator : "";

                            if (empty($title))
                                $title = $bookid;

                            $bookname = $title;

                            if (!empty($creator))
                                $bookname = $creator." - ".$title;
                            
                            // BEGIN Add book 
                            $collector->changeLevel(1);
                            $node = (object)array(
                                'id'         => $menuparent->id,
                                'name'       => $bookname,
                                'uid'        => $menuparent->uid . '_' . $bookid,
                                'link'       => 'index.php?option=com_ortholib&view=article&bookid='.$bookid 
                            );
                            $collector->printNode($node);
                            // END Add book
                            
                            if (!empty($contentopfxml->manifest))
                            {
                                foreach ($contentopfxml->manifest->item as $item)
                                {
                                    if ($item['id'] == 'ncx' && isset($item['href']))
                                    {
                                        $toc_ncx_file = $oebpsdir.DIRECTORY_SEPARATOR.$item['href'];
                                        if (file_exists($toc_ncx_file))
                                        {
                                            $toc_ncx_xml = simplexml_load_file($toc_ncx_file);
                                            
                                            //parse book TOC
                                            $this->parseNavPoint($toc_ncx_xml->navMap->navPoint, $bookid, $collector, $menuparent);

                                            unset($toc_ncx_xml);
                                        }
                                    }
                                }
                            }

                            $collector->changeLevel(-1);
                        }

                        unset($contentopfxml);
                    }
                }

                unset($containerxml);
            }
        }
    }
    
    // Recursively print the book TOC
    private function parseNavPoint(SimpleXMLElement $navPoints, string $bookid, Collector $collector, Item $menuparent) 
    {
        foreach ($navPoints as $navPoint)
        {
            if (!empty($navPoint->navLabel->text))
            {
                $navpoint_id = (string)$navPoint["id"];
                //BEGIN add article
                $collector->changeLevel(1);
                $node = (object)array(
                    'id'         => $menuparent->id,
                    'name'       => $navPoint->navLabel->text,
                    'uid'        => $menuparent->uid . '_' . $bookid . '_' . $navpoint_id,
                    'link'       => 'index.php?option=com_ortholib&view=article&bookid='.$bookid."&navpoint=".$navpoint_id
                );
                $collector->printNode($node);
                $collector->changeLevel(-1);
                //END add article
            }
            
            if (!empty($navPoint->navPoint))
            {
                $this->parseNavPoint($navPoint->navPoint, $bookid, $collector, $menuparent);
            }
        }
    }
}