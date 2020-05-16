<?php

declare(strict_types=1);

namespace Happyr;

use Doctrine\Common\Persistence\ManagerRegistry as LegacyManagerRegistry;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\NoResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UuidParamConverter implements ParamConverterInterface
{
    /**
     * @var ManagerRegistry
     */
    private $registry;

    public function __construct($registry = null)
    {
        if ($registry !== null && !$registry instanceof LegacyManagerRegistry && !$registry instanceof ManagerRegistry) {
            throw new \LogicException(sprintf('First argument to "%s::__construct" must be instance of "%s" or "%s".', __CLASS__, ManagerRegistry::class, LegacyManagerRegistry::class));
        }

        $this->registry = $registry;
    }

    public function apply(Request $request, ParamConverter $configuration)
    {
        $name = $configuration->getName();
        $class = $configuration->getClass();
        $options = $this->getOptions($configuration);

        if (null === $request->attributes->get($name, false)) {
            $configuration->setIsOptional(true);
        }

        $errorMessage = null;
        if (false === $object = $this->find($class, $request, $options, $name)) {
            // We could not find anything
            return false;
        }

        if (null === $object && false === $configuration->isOptional()) {
            $message = \sprintf('%s object not found by the @%s annotation.', $class, $this->getAnnotationName($configuration));
            if ($errorMessage) {
                $message .= ' '.$errorMessage;
            }
            throw new NotFoundHttpException($message);
        }

        $request->attributes->set($name, $object);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ParamConverter $configuration)
    {
        // if there is no manager, this means that only Doctrine DBAL is configured
        if (null === $this->registry || !\count($this->registry->getManagers())) {
            return false;
        }

        if (null === $configuration->getClass()) {
            return false;
        }

        $options = $this->getOptions($configuration);

        // Doctrine Entity?
        $em = $this->getManager($options['entity_manager'], $configuration->getClass());
        if (null === $em) {
            return false;
        }

        return !$em->getMetadataFactory()->isTransient($configuration->getClass());
    }

    private function find($class, Request $request, $options, $name)
    {
        // If a mapping exists we should not try to auto detect this
        if ($options['mapping'] || $options['exclude']) {
            return false;
        }

        if (null === $uuid = $this->getUuid($request, $name)) {
            return false;
        }

        try {
            return $this->getManager($options['entity_manager'], $class)->getRepository($class)->findOneBy(['uuid' => $uuid]);
        } catch (NoResultException $e) {
            return;
        } catch (ConversionException $e) {
            return;
        }
    }

    private function getOptions(ParamConverter $configuration)
    {
        $defaultValues = [
            'entity_manager' => null,
            'id' => null,
            'exclude' => [],
            'mapping' => [],
        ];

        $passedOptions = $configuration->getOptions();

        return \array_replace($defaultValues, $passedOptions);
    }

    private function getManager($name, $class)
    {
        if (null === $name) {
            return $this->registry->getManagerForClass($class);
        }

        return $this->registry->getManager($name);
    }

    private function getUuid(Request $request, $name)
    {
        $keys = [\sprintf('%s_uuid', $name), \sprintf('%sUuid', $name), 'uuid'];
        foreach ($keys as $key) {
            if ($request->attributes->has($key)) {
                return $request->attributes->get($key);
            }
        }

        return null;
    }

    private function getAnnotationName(ParamConverter $configuration)
    {
        $r = new \ReflectionClass($configuration);

        return $r->getShortName();
    }
}
