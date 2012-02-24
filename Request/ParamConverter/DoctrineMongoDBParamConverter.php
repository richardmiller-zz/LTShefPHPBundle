<?php

namespace LT\ShefPHPBundle\Request\ParamConverter;

use Doctrine\ODM\MongoDB\DocumentManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DoctrineMongoDBParamConverter implements ParamConverterInterface
{
    private $dm;

    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    public function apply(Request $request, ConfigurationInterface $configuration)
    {
        if ($request->attributes->has($configuration->getName())) {
            return;
        }

        $class = $configuration->getClass();

        if (false === $object = $this->find($class, $request)) {
            if (false === $object = $this->findOneBy($class, $request)) {
                throw new \LogicException('Unable to guess how to get a Doctrine instance from the request information.');
            }
        }

        if (null === $object && false === $configuration->isOptional()) {
            throw new NotFoundHttpException(sprintf('%s object not found.', $class));
        }

        $request->attributes->set($configuration->getName(), $object);
    }

    public function supports(ConfigurationInterface $configuration)
    {
        if (null === $configuration->getClass()) {
            return false;
        }

        try {
            $this->dm->getClassMetadata($configuration->getClass());

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function find($class, Request $request)
    {
        if (!$request->attributes->has('id')) {
            return false;
        }

        return $this->dm->find($class, $request->attributes->get('id'));
    }

    private function findOneBy($class, Request $request)
    {
        $criteria = array();
        $metadata = $this->dm->getClassMetadata($class);
        foreach ($request->attributes->all() as $key => $value) {
            if ($metadata->hasField($key)) {
                $criteria[$key] = $value;
            }
        }

        if (!$criteria) {
            return false;
        }

        return $this->dm->getRepository($class)->findOneBy($criteria);
    }
}
