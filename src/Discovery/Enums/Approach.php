<?php

declare(strict_types=1);

namespace LaraGram\Brain\Discovery\Enums;

enum Approach: string
{
    case MassAssignmentFillable = 'mass-assignment-fillable';
    case MassAssignmentGuarded = 'mass-assignment-guarded';
    case EnumCaseScreamingSnake = 'enum-case-screaming-snake';
    case EnumCasePascal = 'enum-case-pascal';
    case EnumCaseCamel = 'enum-case-camel';
    case ValidationPipeSyntax = 'validation-pipe-syntax';
    case ValidationArraySyntax = 'validation-array-syntax';
    case ValidationInline = 'validation-inline';
    case ValidationFormRequest = 'validation-form-request';
    case CommandAttributeSyntax = 'command-attribute-syntax';
    case CommandPropertySyntax = 'command-property-syntax';
    case AuthorizationGate = 'authorization-gate';
    case AuthorizationUserCan = 'authorization-user-can';
    case AuthorizationTrait = 'authorization-trait';
    case AuthFacade = 'auth-facade';
    case AuthRequest = 'auth-request';
    case AuthHelper = 'auth-helper';
    case ModelUuidKeys = 'model-uuid-keys';
    case ModelUlidKeys = 'model-ulid-keys';
    case ModelIncrementingKeys = 'model-incrementing-keys';
    case ListenActionClosure = 'listen-action-closure';
    case ListenActionController = 'listen-action-controller';
    case UpdateAccessHelpers = 'update-access-helpers';
    case UpdateAccessRequest = 'update-access-request';
    case KeyboardBuilder = 'keyboard-builder';
    case KeyboardArray = 'keyboard-array';
    case ReplyTemplate = 'reply-template';
    case ReplyDirect = 'reply-direct';
    case MultiStepConversation = 'multi-step-conversation';
    case MultiStepStepManager = 'multi-step-step-manager';
}
