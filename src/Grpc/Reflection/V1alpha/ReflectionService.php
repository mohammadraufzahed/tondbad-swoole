<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Reflection\V1alpha;

use Google\Protobuf\Internal\FileDescriptorProto;
use TondbadSwoole\Grpc\AttributeServiceAdapter;
use TondbadSwoole\Grpc\Attributes\AsGrpcService;
use TondbadSwoole\Grpc\Attributes\GrpcMethod;
use TondbadSwoole\Grpc\Context;
use TondbadSwoole\Grpc\ServiceRegistry;
use TondbadSwoole\Grpc\Status;
use TondbadSwoole\Grpc\StatusException;
use TondbadSwoole\Grpc\StreamReader;
use TondbadSwoole\Grpc\StreamWriter;

#[AsGrpcService('grpc.reflection.v1alpha.ServerReflection', 'grpc.reflection.v1alpha')]
class ReflectionService extends AttributeServiceAdapter
{
    /** @var array<string, ReflectionIndex> */
    private static array $indexes = [];

    #[GrpcMethod('ServerReflectionInfo', ServerReflectionRequest::class, ServerReflectionResponse::class, clientStreaming: true, serverStreaming: true)]
    public function serverReflectionInfo(StreamReader $stream, StreamWriter $writer, Context $context): void
    {
        $index = $this->index($context);
        $registry = $context->getValue(ServiceRegistry::class);
        $registeredServices = [];

        if ($registry instanceof ServiceRegistry) {
            $registeredServices = array_keys($registry->all());
        }

        while (($request = $stream->recv()) !== null) {
            if (!$request instanceof ServerReflectionRequest) {
                continue;
            }

            $response = $this->process($request, $index, $registeredServices);
            $response->setHost($request->getHost());
            $response->setOriginalRequest($request->getOriginalRequest());
            $writer->write($response);
        }
    }

    private function process(ServerReflectionRequest $request, ReflectionIndex $index, array $registeredServices): ServerReflectionResponse
    {
        $response = new ServerReflectionResponse();

        if ($request->hasListServices()) {
            $list = new ListServiceResponse();

            foreach ($index->listServices($registeredServices) as $serviceName) {
                $service = new ServiceResponse();
                $service->setName($serviceName);
                $list->getService()[] = $service;
            }

            $response->setListServicesResponse($list);

            return $response;
        }

        if ($request->hasFileByFilename()) {
            $file = $index->fileByName($request->getFileByFilename()->getFileName());

            return $file !== null
                ? $this->fileResponse($response, $file, $index)
                : $this->errorResponse($response, 'File not found.');
        }

        if ($request->hasFileContainingSymbol()) {
            $file = $index->fileContainingSymbol($request->getFileContainingSymbol());

            return $file !== null
                ? $this->fileResponse($response, $file, $index)
                : $this->errorResponse($response, 'Symbol not found.');
        }

        if ($request->hasFileContainingExtension()) {
            $req = $request->getFileContainingExtension();
            $file = $index->fileContainingExtension($req->getContainingType(), $req->getExtensionNumber());

            return $file !== null
                ? $this->fileResponse($response, $file, $index)
                : $this->errorResponse($response, 'Extension not found.');
        }

        if ($request->hasAllExtensionNumbersOfType()) {
            $numbers = $index->allExtensionNumbersOfType($request->getAllExtensionNumbersOfType());
            $ext = new ExtensionNumberResponse();
            $ext->setBaseTypeName($request->getAllExtensionNumbersOfType());

            foreach ($numbers as $number) {
                $ext->getExtensionNumber()[] = $number;
            }

            $response->setAllExtensionNumbersResponse($ext);

            return $response;
        }

        return $this->errorResponse($response, 'Unsupported reflection request.');
    }

    private function fileResponse(ServerReflectionResponse $response, FileDescriptorProto $file, ReflectionIndex $index): ServerReflectionResponse
    {
        $files = $index->withDependencies($file);
        $fileDescriptor = new FileDescriptorResponse();

        foreach ($files as $f) {
            $fileDescriptor->getFileDescriptorProto()[] = $f->serializeToString();
        }

        $response->setFileDescriptorResponse($fileDescriptor);

        return $response;
    }

    private function errorResponse(ServerReflectionResponse $response, string $message): ServerReflectionResponse
    {
        $error = new ErrorResponse();
        $error->setErrorCode(Status::notFound()->code);
        $error->setErrorMessage($message);
        $response->setErrorResponse($error);

        return $response;
    }

    private function index(Context $context): ReflectionIndex
    {
        $path = (string) $context->getValue('tondbad.grpc.descriptor_set');

        if ($path === '') {
            throw new StatusException(Status::unimplemented('Descriptor set path not configured'));
        }

        return self::$indexes[$path] ??= new ReflectionIndex($path);
    }
}
