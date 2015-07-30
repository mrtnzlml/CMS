<?php

namespace Pages\Components\PagesGrid;

use App\Components\AControl;
use Kdyby\Doctrine\EntityManager;
use Nette;
use Nette\Application\UI;
use Pages\Page;
use Pages\PageFacade;
use Pages\Query\PagesQueryAdmin;

class PagesGrid extends AControl
{

	/** @persistent */
	public $category_id = NULL;

	/** @persistent */
	public $tag_id = NULL;

	/** @persistent */
	public $author_id = NULL;

	/** @var EntityManager */
	private $em;

	private $pages;

	/** @var PageFacade */
	private $pageFacade;

	public function __construct(EntityManager $em, PageFacade $pageFacade)
	{
		$this->em = $em;
		$this->pageFacade = $pageFacade;
	}

	/** @param $presenter UI\Presenter */
	public function attached($presenter)
	{
		parent::attached($presenter);

		$query = (new PagesQueryAdmin())
			->withCategories($this->category_id)
			->withTags($this->tag_id)
			->withAuthors($this->author_id);
		$this->pages = $this->em->getRepository(Page::class)->fetch($query);
		$this->pages->applyPaging(0, 100)->setFetchJoinCollection(FALSE);
	}

	public function render(array $parameters = NULL)
	{
		if ($parameters) {
			$this->template->parameters = Nette\Utils\ArrayHash::from($parameters);
		}
		$this->template->pages = $this->pages;
		$this->template->render($this->templatePath ?: __DIR__ . '/PagesGrid.latte');
	}

	protected function createComponentGridForm()
	{
		$form = new UI\Form();
		$form->addProtection();

		$checkboxes = $form->addContainer('page');

		$categories = $tags = $authors = [];
		/** @var Page $page */
		foreach ($this->pages as $page) {
			$checkboxes->addCheckbox($page->id, NULL);
			foreach ($page->categories as $category) { //FIXME: toto není moc pěkné
				$categories[$category->getId()] = $category->getName();
			}
			foreach ($page->tags as $tag) { //FIXME: toto není moc pěkné
				$tags[$tag->getId()] = $tag->getName();
			}
			foreach ($page->authors as $author) { //FIXME: toto není moc pěkné
				$authors[$author->getId()] = $author->email;
			}
		}

		$form->addSelect('actionAbove', NULL, $actions = [
			NULL => 'Hromadné úpravy',
			'edit' => 'Editovat',
			'delete' => 'Smazat', //TODO js:alert
		]);
		$form->addSelect('actionBelow', NULL, $actions);

		$form->addSelect('categories', NULL, [
				NULL => 'Kategorie',
			] + $categories)->setDefaultValue($this->category_id);
		$form->addSelect('tags', NULL, [
				NULL => 'Štítky',
			] + $tags)->setDefaultValue($this->tag_id);
		$form->addSelect('authors', NULL, [
				NULL => 'Autor',
			] + $authors)->setDefaultValue($this->author_id);

//		$form->addSubmit('submit');

		$form->onSuccess[] = $this->gridFormSucceeded;
		return $form;
	}

	public function gridFormSucceeded($_, Nette\Utils\ArrayHash $values)
	{
		$this->category_id = $values->categories;
		$this->tag_id = $values->tags;
		$this->author_id = $values->authors;

		$multiEdit = [];
		foreach ($values->page as $id => $checked) {
			if ($checked) {
				$multiEdit[$id] = $id;
			}
		}
		$action = $values->actionBelow ?: $values->actionAbove;
		if ($action === 'edit') {
			$this->presenter->redirect(':Pages:AdminPage:multiEdit', [$multiEdit]);
		} elseif ($action === 'delete') {
			$this->pageFacade->onRemove[] = function () {
				$this->em->flush();
				$this->redirect('this');
			};
			$this->pageFacade->remove($multiEdit);
		} else {
			$this->redirect('this');
		}
	}

	/**
	 * @secured
	 * @deprecated
	 */
	public function handleDelete($id)
	{
		//TODO: is user allowed to delete this page? (same with edit)
		$this->pageFacade->onRemove[] = function (PageFacade $process, Page $page) {
			$this->em->flush();
			$this->redirect('this');
		};
		$this->pageFacade->remove($id);
	}

}

interface IPagesGridFactory
{
	/** @return PagesGrid */
	function create();
}
