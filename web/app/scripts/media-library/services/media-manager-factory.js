(function (angular) {
'use strict';

angular.module('bns.mediaLibrary.mediaManager', [])

  .factory('mediaManager', MediaManagerFactory)

;

function MediaManagerFactory () {

  return {
    // map of media types, from API
    types: {
      IMAGE: 'image',
      VIDEO: 'video',
      DOCUMENT: 'document',
      AUDIO: 'audio',
      LINK: 'link',
      EMBEDDED_VIDEO: 'embed',
      FILE: 'file',
      PROVIDER_RESOURCE: 'provider',
      ATELIER_DOCUMENT: 'workshop_document',
      HTML: 'html',
      HTML_BASE: 'html_base',
      ATELIER_AUDIO: 'workshop_audio',
    },

    isFile: function (obj) {
      return obj && obj.type_unique_name;
    },

    getMediaType: function (obj) {
      return this.types[obj.type_unique_name];
    },

    isMediaImage: function (obj) {
      return 'image' === this.getMediaType(obj);
    },

    isMediaVideo: function (obj) {
      return 'video' === this.getMediaType(obj);
    },

    isMediaDocument: function (obj) {
      return 'document' === this.getMediaType(obj);
    },

    isMediaAudio: function (obj) {
      return 'audio' === this.getMediaType(obj);
    },

    isMediaLink: function (obj) {
      return 'link' === this.getMediaType(obj);
    },

    isMediaFile: function (obj) {
      return 'file' === this.getMediaType(obj);
    },

    isMediaEmbed: function (obj) {
      return 'embed' === this.getMediaType(obj);
    },

    isMediaProvider: function (obj) {
      return 'provider' === this.getMediaType(obj);
    },

    isMediaWorkshopDocument: function (obj) {
      return 'workshop_document' === this.getMediaType(obj);
    },

    isMediaHtml: function (obj) {
      return 'html' === this.getMediaType(obj);
    },

    isMediaHtmlBase: function (obj) {
      return 'html_base' === this.getMediaType(obj);
    },

    isMediaWorkshopAudio: function (obj) {
      return 'workshop_audio' === this.getMediaType(obj);
    },

    isFolder: function (obj) {
      return obj && obj.type;
    },

    isUserFolder: function (obj) {
      return this.isFolder(obj) && 'USER' === obj.type;
    },

    isGroupFolder: function (obj) {
      return this.isFolder(obj) && 'GROUP' === obj.type;
    },

    isSchoolFolder: function (obj) {
      return this.isFolder(obj) && 'SCHOOL' === obj.group_type;
    },

    isClassFolder: function (obj) {
      return this.isFolder(obj) && 'CLASSROOM' === obj.group_type;
    },

    isTeamFolder: function (obj) {
      return this.isFolder(obj) && 'TEAM' === obj.group_type;
    },

    isPartnershipFolder: function (obj) {
      return this.isFolder(obj) && 'PARTNERSHIP' === obj.group_type;
    },

    isLockerFolder: function (obj) {
      return this.isFolder(obj) && obj.is_locker;
    },

    isTrash: function (obj) {
      return 'trash' === obj.role;
    },

    isFavorites: function (obj) {
      return 'favorites' === obj.role;
    },

    isRecents: function (obj) {
      return 'recents' === obj.role;
    },

    isExternal: function (obj) {
      return 'external' === obj.role;
    },

    isSystem: function (obj) {
      return obj.is_system;
    },

    isFromPaas: function (obj) {
      return !!obj.from_paas && parseInt(obj.id) >= 100000000;
    },

    isDownloadable: function (obj) {
      return false !== obj.downloadable;
    }

  };

}

})(angular);
