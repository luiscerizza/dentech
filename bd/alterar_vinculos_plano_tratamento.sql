-- ============================================================
-- DENTECH
-- VÍNCULOS DO PLANO DE TRATAMENTO
-- ============================================================
-- Objetivo:
--   Conectar etapas do plano aos agendamentos e procedimentos
--   sem quebrar registros antigos.
--
-- Todos os novos vínculos são opcionais (NULL), permitindo que
-- registros existentes continuem funcionando normalmente.
-- ============================================================


/*
|--------------------------------------------------------------------------
| 1. AGENDAMENTOS -> ETAPA DO PLANO
|--------------------------------------------------------------------------
*/

ALTER TABLE agendamentos
    ADD COLUMN plano_item_id INT NULL,
    ADD INDEX idx_agendamento_plano_item (plano_item_id),
    ADD CONSTRAINT fk_agendamento_plano_item
        FOREIGN KEY (plano_item_id)
        REFERENCES planos_tratamento_itens (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;


/*
|--------------------------------------------------------------------------
| 2. PROCEDIMENTOS -> ETAPA DO PLANO
|--------------------------------------------------------------------------
*/

ALTER TABLE procedimentos
    ADD COLUMN plano_item_id INT NULL,
    ADD INDEX idx_procedimento_plano_item (plano_item_id),
    ADD CONSTRAINT fk_procedimento_plano_item
        FOREIGN KEY (plano_item_id)
        REFERENCES planos_tratamento_itens (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;


/*
|--------------------------------------------------------------------------
| 3. PROCEDIMENTOS -> AGENDAMENTO
|--------------------------------------------------------------------------
*/

ALTER TABLE procedimentos
    ADD COLUMN agendamento_id INT NULL,
    ADD INDEX idx_procedimento_agendamento (agendamento_id),
    ADD CONSTRAINT fk_procedimento_agendamento
        FOREIGN KEY (agendamento_id)
        REFERENCES agendamentos (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;