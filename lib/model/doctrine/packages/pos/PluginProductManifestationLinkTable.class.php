<?php

/**
 * PluginProductManifestationLinkTable
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class PluginProductManifestationLinkTable extends ProductLinkTable
{
    /**
     * Returns an instance of this class.
     *
     * @return object PluginProductManifestationLinkTable
     */
    public static function getInstance()
    {
        return Doctrine_Core::getTable('PluginProductManifestationLink');
    }
}