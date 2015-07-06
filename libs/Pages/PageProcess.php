<?php

namespace Pages;

use Kdyby\Doctrine\EntityManager;
use Nette;
use Url\RouteGenerator as Url;
use Users\User;

/**
 * @method onSave(PageProcess $control, Page $entity)
 * @method onPublish(PageProcess $control, Page $entity)
 */
class PageProcess extends Nette\Object
{

	/** @var \Closure[] */
	public $onPublish = [];

	/** @var \Closure[] */
	public $onSave = [];

	/** @var EntityManager */
	private $em;
	/** @var \Kdyby\Doctrine\EntityRepository */
	private $articles;
	/** @var Nette\Security\IUserStorage */
	private $user;

	public function __construct(EntityManager $em, Nette\Security\IUserStorage $user)
	{
		$this->em = $em;
		$this->articles = $em->getRepository(Page::class);
		$this->user = $user;
	}

	public function publish(Page $page)
	{
		$page->publishedAt = new \DateTime();
		$this->completePageEntity($page);

		if (!$page->url) {
			$url = Url::generate(Nette\Utils\Strings::webalize($page->title), 'Front:Page:default', $page->getId());
			$page->setUrl($url); //UniqueConstraintViolationException
		}

		$this->em->persist($page);
		$this->onPublish($this, $page);
		// don't forget to call $em->flush() in your control
	}

	public function save(Page $page)
	{
		$this->completePageEntity($page);

		//FIXME: pokud by bylo potřeba vytvořit novou URL adresu, musí se stará přesměrovávat na tuto novou...
		if (!$page->url) {
			$url = Url::generate(Nette\Utils\Strings::webalize($page->title), 'Front:Page:default', $page->getId());
			$page->setUrl($url); //UniqueConstraintViolationException
		}

		$this->em->persist($page);
		$this->onSave($this, $page);
		// don't forget to call $em->flush() in your control
	}

	//TODO: delete (přijímá i pole a iteruje jej)

	private function completePageEntity(Page $page)
	{
		if ($page->getTitle() === NULL) {
			throw new Nette\InvalidArgumentException('You must set title of the page.');
		}
		if ($page->getBody() === NULL) {
			throw new Nette\InvalidArgumentException('You must set body of the page.');
		}
		$realAuthorReference = $this->em->getPartialReference(User::class, $this->user->getIdentity()->getId());
		$page->setRealAuthor($realAuthorReference);
	}

}
