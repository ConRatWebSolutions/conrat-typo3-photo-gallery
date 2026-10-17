#
# Table structure for table 'tx_crgalerie_domain_model_kategorie'
#
CREATE TABLE tx_crgalerie_domain_model_kategorie (

	titel varchar(255) DEFAULT '' NOT NULL,

);

#
# Table structure for table 'tx_crgalerie_domain_model_galerie'
#
CREATE TABLE tx_crgalerie_domain_model_galerie (

	titel varchar(255) DEFAULT '' NOT NULL,
	beschreibung text,
	fotograf varchar(255) DEFAULT '' NOT NULL,
	aufnahmezeitpunkt datetime DEFAULT NULL,
	sortierung int(11) DEFAULT '0' NOT NULL,
	kategorie int(11) unsigned DEFAULT '0',
	bilder int(11) unsigned DEFAULT '0' NOT NULL,

);

#
# Table structure for table 'tx_crgalerie_domain_model_bilder'
#
CREATE TABLE tx_crgalerie_domain_model_bilder (

	titel varchar(255) DEFAULT '' NOT NULL,
	bildunterschrift text,
	sortierung int(11) DEFAULT '0' NOT NULL,
	datei int(11) unsigned NOT NULL default '0',
	galerie int(11) unsigned DEFAULT '0',

);
