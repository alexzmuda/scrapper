<?php

include APPLICATION_PATH . "/../classes/core/simple_html_dom.php";

class ScrapperRobot {

	var $scrapper;
	var $html;
	var $domain;
	var $page_url;
	var $subpages;
	var $prefix;
	var $products = array();

	var $scheme = array();

	// products_links_class = ''; // li.views-row div.more-info
	// name = ''; // h1.title
	// subtitle = ''; // #sub-title
	// product_code = ''; // 'h1.title'
	// image = ''; // 'a.fancybox-image'
	// image = ''; // 'a.fancybox-image'
	// description = ''; // '#description'
	// description = ''; // '#description'

	function __construct() {
		ini_set("memory_limit", "256M");

	}

	public function setScrapper($s) {
		$this->scrapper = $s;
		$this->domain = $s['domain'];
		$this->page_url = $s['url'];
		$this->prefix = $s['slug'];

		// set scrapp scheme
		$scheme['products_links_class'] = $s['scheme_products_links_class'];
		$scheme['name'] = $s['scheme_name'];
		$scheme['subtitle'] = $s['scheme_subtitle'];
		$scheme['product_code'] = $s['scheme_product_code'];
		$scheme['image'] = $s['scheme_image'];
		$scheme['intro'] = $s['scheme_intro'];
		$scheme['description'] = $s['scheme_description'];

		// scheme for features
		$scheme['scheme_features_1'] = $s['scheme_features_1'];
		$scheme['scheme_features_2'] = $s['scheme_features_2'];
		$scheme['scheme_features_idx'] = $s['scheme_features_idx'];

		// scheme for specification
		$scheme['scheme_spec_label'] = $s['scheme_spec_label'];
		$scheme['scheme_spec_unit'] = $s['scheme_spec_unit'];
		$scheme['scheme_spec_value'] = $s['scheme_spec_value'];

		$scheme['scheme_spec_label_idx'] = $s['scheme_spec_label_idx'];
		$scheme['scheme_spec_unit_idx'] = $s['scheme_spec_unit_idx'];
		$scheme['scheme_spec_value_idx'] = $s['scheme_spec_value_idx'];

		$scheme['scheme_spec_label_el'] = $s['scheme_spec_label_el'];
		$scheme['scheme_spec_unit_el'] = $s['scheme_spec_unit_el'];
		$scheme['scheme_spec_value_el'] = $s['scheme_spec_value_el'];

		$this->scheme = $scheme;

	}

	function output($msg) {
		print "<p>" . $msg . "</p>";
	}


	function scrapIndex() {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, '');
		$ch = curl_init($this->page_url);
		$fp = fopen(APPLICATION_PATH . "/../data/scrapped/" . $this->prefix . ".txt", "w");

		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);

		$output = curl_exec($ch);
		curl_close($ch);
		fclose($fp);

		$this->html = file_get_html(APPLICATION_PATH . '/../data/scrapped/' . $this->prefix . '.txt');
		$this->output('Scrapped index page ' . $this->page_url);
	}

	function scrapProductsLinks() {
		# get products list first
		$subpages = array();
		foreach ($this->html->find($this->scheme['products_links_class']) as $article) {
			$item['link'] = $article->find('a', 0)->href;
			if ($item['link'] == '') {
				// empty
			} else {
				if (!strstr($item['link'], '://')) {
					$item['link'] = $this->domain . $article->find('a', 0)->href;
				}

				$subpages[] = $item;

				$this->output('Found product page ' . $item['link']);
			}
		}
		//pre($subpages);
		//exit;
		$this->subpages = $subpages;
	}

	function scrapProductsPages() {
		if (count($this->subpages) > 0) {
			foreach ($this->subpages as $idx => $item) {

				# scrap individual page
				$p_file = $this->prefix . '-' . md5($item['link']);
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, '');
				$ch = curl_init($item['link']);
				$fp = fopen(APPLICATION_PATH . "/../data/scrapped/" . $p_file . ".txt", "w");

				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_HEADER, 0);

				$output = curl_exec($ch);
				curl_close($ch);
				fclose($fp);

				if (filesize(APPLICATION_PATH . "/../data/scrapped/" . $p_file . ".txt") == 0) {
					@unlink(APPLICATION_PATH . "/../data/scrapped/" . $p_file . ".txt");
					unset($this->subpages[$idx]);
				}

				$this->output('Scrapped product page ' . $item['link']);

			}
		}
	}

	function scrapProducts() {
		foreach ($this->subpages as $idx => $item) {
			$p_file = $this->prefix . '-' . md5($item['link']);

			# process page
			$html = file_get_html(APPLICATION_PATH . '/../data/scrapped/' . $p_file . '.txt');

			$sp = array();


			$sp['link'] = $item['link'];
			# basic elements
			$sp['fk_scrapper_id'] = $this->scrapper['id'];
			$sp['fk_supplier_id'] = $this->scrapper['fk_supplier_id'];
			$sp['fk_product_category_id'] = $this->scrapper['fk_category_id'];

			$sp['name'] = $html->find($this->scheme['name'], 0)->plaintext;
			if ($this->scheme['subtitle'] != '') {
				$sp['subtitle'] = $html->getElementById($this->scheme['subtitle'])->plaintext;
			} else {
				$sp['subtitle'] = '';
			}
			if ($this->scheme['subtitle'] != '') {
				$sp['product_code'] = $html->find($this->scheme['product_code'], 0)->plaintext;
			} else {
				$sp['product_code'] = '';
			}

			$sp['intro'] = '';

			$s = explode("|", $this->scheme['image']);

			$prefix = $s[1];

			if ($prefix == 'src') {
				$sp['image'] = $html->find($s[0], 0)->src;
			} else {
				$sp['image'] = $html->find($s[0], 0)->href;
			}


			if (!strstr($sp['image'], '://')) {
				$sp['image'] = $this->domain . $sp['image'];
			}

			if ($this->scrapper['standard_scrapper'] == 0) {
				$sp['description'] = $html->getElementById($this->scheme['description'])->plaintext;
				// best
				# features
				$sp['features'] = array();

				// option for 2 params
				if ($this->scheme['scheme_features_1'] != '' && $this->scheme['scheme_features_2'] != '') {
					foreach ($html->find($this->scheme['scheme_features_1'], $this->scheme['scheme_features_idx'])->find($this->scheme['scheme_features_2']) as $element) {
						$sp['features'][] = $element->plaintext;
					}
				}
				// option if only one param
				if ($this->scheme['scheme_features_1'] != '' && $this->scheme['scheme_features_2'] == '') {
					foreach ($html->find($this->scheme['scheme_features_1'], $this->scheme['scheme_features_idx']) as $element) {
						$sp['features'][] = $element->plaintext;
					}
				}


				# specifications
				$i = 0;
				$x = array();
				$sp['specification'] = array();
				if ($this->scheme['scheme_spec_label'] != '') {

					foreach ($html->find($this->scheme['scheme_spec_label']) as $element) {
						if ($this->scheme['scheme_spec_label_idx'] > 0) {
							$x[$i]['label'] = trim($element->children($this->scheme['scheme_spec_label_idx'])->plaintext);
						} else {
							$x[$i]['label'] = trim($element->plaintext);
						}

						$i++;
					}
					$i = 0;

					foreach ($html->find($this->scheme['scheme_spec_value']) as $element) {
						if ($this->scheme['scheme_spec_value_idx'] > 0) {
							$x[$i]['value'] = trim($element->children($this->scheme['scheme_spec_value_idx'])->plaintext);
						} else {
							$x[$i]['value'] = trim($element->plaintext);
						}

						$i++;
					}
					$i = 0;

					foreach ($html->find($this->scheme['scheme_spec_unit']) as $element) {
						if ($this->scheme['scheme_spec_unit_idx'] > 0) {
							$x[$i]['unit'] = trim($element->children($this->scheme['scheme_spec_unit_idx'])->plaintext);
						} else {
							$x[$i]['unit'] = trim($element->plaintext);
						}

						$i++;
					}
					$i = 0;


					$sp['specification'] = $x;
				}
			}

			if ($this->scrapper['standard_scrapper'] == 1) {
				//pre($this->scheme['description']);
				// middle
				$sp['description'] = $html->find('#product', 0)->children(2)->children(1)->outertext;
				//$html->find('#product', 0)->children(2)->children(1)->outertext;
				//$html->find('div.content#views-table cols-136', 0)->children(2)->children(1)->outertext;
				$sp['specification'] = $html->find('table.views-table', 0)->outertext;
				//	$sp['features'] = $html->getElementById($this->scheme['scheme_spec_raw'])->outertext;
			}


			if ($this->scrapper['standard_scrapper'] == 2) {
				//pre($this->scheme['description']);
				// middle
				$sp['description'] = $html->find('#product', 0)->children(2)->children(1)->outertext;
				//$html->find('#product', 0)->children(2)->children(1)->outertext;
				//$html->find('div.content#views-table cols-136', 0)->children(2)->children(1)->outertext;
				$sp['specification'] = $html->find('table.views-table', 0)->outertext;
				//	$sp['features'] = $html->getElementById($this->scheme['scheme_spec_raw'])->outertext;
			}

			# images

			# files

			# prices

			# assign to array
			$products[] = $sp;
			$html = null;

			$this->output('Processed product page ' . $p_file . '.txt');

		}
		$this->products = $products;
	}


	function uploadProducts() {
		// delete all products by scrapper id
		// change  - no delete but just ADD new
		// Doctrine::getTable('Product')->getByScrapperId($this->scrapper['id'], Doctrine::HYDRATE_RECORD)->delete();

		//pre($this->products);
		//exit;

		// import products
		foreach ($this->products as $p) {
			// check if product exists by name, scrapper and supplier
			if (Doctrine::getTable('Product')->alreadyExists($p['name'], $p['fk_scrapper_id'], $p['fk_supplier_id']) == false) {

				$x = new Product();
				$x->fk_scrapper_id = $p['fk_scrapper_id'];
				$x->fk_supplier_id = $p['fk_supplier_id'];
				//$x->fk_product_category_id = $p['fk_product_category_id'];
				$x->name = $p['name'];
				$x->subtitle = $p['subtitle'];
				$x->image = $p['image'];
				$x->org_image = $p['image'];
				$x->product_code = $p['product_code'];
				$x->intro = $p['intro'];
				$x->description = $p['description'];

				if ($this->scrapper['standard_scrapper'] == 1) {
					$x->text_spec = $p['specification'];
				}

				$x->save();


				/// multiple categorisation
				foreach ($this->scrapper['categories'] as $c) {
					if (isset($c['id']) && ($c['id'] > 0)) {
						$ppca = new ProductProductCategoryAgent();
						$ppca->fk_product_id = $x->id;
						$ppca->fk_product_category_id = $c['id'];
						$ppca->save();
						$ppca->free();
					}
				}
				if ($this->scrapper['standard_scrapper'] == 0) {
					foreach ($p['features'] as $k => $v) {
						$xf = new ProductFeature();
						$xf->fk_product_id = $x->id;
						$xf->name = $v;
						$xf->save();
						$xf->free();
					}

					foreach ($p['specification'] as $s) {
						$xf = new ProductSpecification();
						$xf->fk_product_id = $x->id;
						$xf->name = $s['label'];
						$xf->unit = $s['unit'];
						$xf->value = $s['value'];
						$xf->save();
						$xf->free();
					}
					$x->free();
				}

				$this->output('Uploaded product ' . $p['name']);
			}
		}

	}
}