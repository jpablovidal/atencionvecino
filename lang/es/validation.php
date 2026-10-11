<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'max' => ['string' => 'El campo :attribute no puede superar los :max caracteres.', 'numeric' => 'El campo :attribute no puede ser mayor que :max.', 'array' => 'El campo :attribute admite como máximo :max elementos.', 'file' => 'El archivo :attribute no puede superar :max KB.'],
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.', 'numeric' => 'El campo :attribute debe ser al menos :min.', 'array' => 'El campo :attribute requiere al menos :min elementos.', 'file' => 'El archivo :attribute debe pesar al menos :min KB.'],
    'in' => 'El valor de :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'between' => ['numeric' => 'El campo :attribute debe estar entre :min y :max.'],
    'exists' => 'El valor de :attribute no existe.',
    'unique' => 'El valor de :attribute ya está registrado.',
    'regex' => 'El formato de :attribute no es válido.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'after_or_equal' => 'El campo :attribute debe ser posterior o igual a :date.',
    'array' => 'El campo :attribute debe ser una lista.',
    'string' => 'El campo :attribute debe ser texto.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'attributes' => [
        'correo' => 'correo', 'detalle' => 'detalle', 'canal_notificacion' => 'forma de notificación', 'observacion' => 'observación', 'plazo_dias' => 'plazo',
        'codigo_cpat' => 'código CPAT', 'nombre' => 'nombre', 'run' => 'RUN', 'motivo' => 'motivo', 'titulo' => 'título', 'texto' => 'texto',
    ],
];
